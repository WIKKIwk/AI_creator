<?php

namespace App\Services\Handler;

use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\HandlerInterface;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\TgBot\TgBot;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;

class BaseHandler implements HandlerInterface
{
    use HandlerTrait;

    protected User $user;

    public function __construct(public TgBot $tgBot, public Cache $cache)
    {
    }

    public function validateUser(User $user): bool
    {
        // Org is active
        return $user->organization_id;
    }

    public function handle(User $user, array $update): void
    {
        if (!$this->validateUser($user)) {
            return;
        }

        $this->user = $user;
        $this->tgBot->setUpdate($update);

        if ($this->tgBot->update['inline_query'] ?? false) {
            $this->handleInlineQuery($this->tgBot->update['inline_query']);
            return;
        }

        $input = $this->tgBot->input;

        // 1️⃣ Handle commands
        if ($input === '/start') {
            $this->handleStart();
            $this->tgBot->settlePromises();
            return;
        }

        if ($input === '/help') {
            $this->handleHelp();
            $this->tgBot->settlePromises();
            return;
        }

        if ($cbData = Arr::get($update, 'callback_query.data')) {
            $this->handleCbQuery($cbData);
            $this->tgBot->settlePromises();
            return;
        }

        $this->tgBot->rmLastMsg();
        if ($input) {
            $this->handleText($input);
        }

        $this->tgBot->settlePromises();
    }

    public function handleInlineQuery($inlineQuery): void
    {
        //
    }

    public function handleStart(): void
    {
        $msg = self::getUserDetailsMsg($this->user);
        $this->tgBot->answerMsg([
            'text' => <<<HTML
<b>Your details:</b>

$msg
HTML,
            'parse_mode' => 'HTML',
        ]);
    }

    public static function getUserDetailsMsg(User $user): string
    {
        $message = '';
        if ($user->organization) {
            $message .= "Organization: <b>{$user->organization->name}</b>\n";
        }
        $message .= "Name: <b>{$user->name}</b>\n";
        $message .= "Email: <b>{$user->email}</b>\n";
        $message .= "Role: <b>{$user->role->getLabel()}</b>\n";
        if ($user->warehouse) {
            $message .= "Warehouse: <b>{$user->warehouse->name}</b>\n";
        }
        if ($user->workStation) {
            $message .= "Work station: <b>{$user->workStation->name}</b>\n";
        }

        return $message;
    }

    public function handleHelp(): void
    {
        //
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function handleCbQuery(string $cbData): void
    {
        $params = [];
        $methodName = $cbData;
        if (preg_match('/^(.*):(.+)$/', $cbData, $matches)) {
            $methodName = $matches[1]; // e.g., 'completeMaterial'
            $params[] = $matches[2];
        }

        $activeSceneHandler = $this->getActiveSceneHandler($this->getScene());
        if ($activeSceneHandler && method_exists($activeSceneHandler, $methodName)) {
            call_user_func([$activeSceneHandler, $methodName], ...$params);
            return;
        }

        if (method_exists($this, $methodName)) {
            call_user_func([$this, $methodName], ...$params);
            return;
        }

        $this->tgBot->answerCbQuery(['text' => 'Invalid callback data.']);
    }

    public function handleText(string $text): void
    {
        //
    }

    public function getActiveSceneHandler($scene = null): ?SceneHandlerInterface
    {
        return null;
    }

    public function getState(): ?string
    {
        return $this->cache->get($this->getCacheKey('state'));
    }

    public function setState(string $state): void
    {
        $this->cache->put($this->getCacheKey('state'), $state);
    }

    public function getScene(): ?string
    {
        return $this->cache->get($this->getCacheKey('scene'));
    }

    public function setScene(string $scene): void
    {
        $this->cache->put($this->getCacheKey('scene'), $scene);
    }

    public function setCache(string $key, mixed $value): void
    {
        $this->cache->put($this->getCacheKey($key), $value);
    }

    public function getCache(string $key): mixed
    {
        return $this->cache->get($this->getCacheKey($key));
    }

    public function forgetCache(string $key): void
    {
        $this->cache->forget($this->getCacheKey($key));
    }

    public function setCacheArray(string $key, array $value): void
    {
        $this->cache->put($this->getCacheKey($key), json_encode($value));
    }

    public function getCacheArray(string $key): ?array
    {
        $value = $this->cache->get($this->getCacheKey($key));
        return $value ? json_decode($value, true) : null;
    }
}
