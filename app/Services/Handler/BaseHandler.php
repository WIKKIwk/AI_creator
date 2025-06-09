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

    public User $user;
    protected array $sceneHandlers = [];
    protected array $callbackHandlers = [];

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
            $this->tgBot->settlePromises();
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

        if ($input === '/cancel') {
            $this->resetCache();
            $this->tgBot->answerMsg(['text' => "Cancelled."]);
            $this->tgBot->settlePromises();
            return;
        }

        if ($cbData = Arr::get($update, 'callback_query.data')) {
            $this->handleCbQuery($cbData);
            $this->tgBot->settlePromises();
            return;
        }

        if ($input) {
            $sceneHandler = $this->getSceneHandler();
            if ($sceneHandler && method_exists($sceneHandler, 'handleText')) {
                $sceneHandler->handleText($input);
            } else {
                $this->handleText($input);
            }
        }

        $this->tgBot->rmLastMsg();
        $this->tgBot->settlePromises();
    }

    public function handleInlineQuery($inlineQuery): void
    {
        $sceneHandler = $this->getSceneHandler();
        if ($sceneHandler && method_exists($sceneHandler, 'handleInlineQuery')) {
            call_user_func([$sceneHandler, 'handleInlineQuery'], $inlineQuery);
            return;
        }

        $this->tgBot->answerInlineQuery([
            'results' => [],
            'cache_time' => 0,
            'is_personal' => true,
        ]);
    }

    public function handleStart(): void
    {
        $this->sendMainMenu();
    }

    public function handleHelp(): void
    {
        $this->tgBot->answerMsg(['text' => "What do you need help with?"]);
    }

    public function handleCbQuery(string $cbData): void
    {
        $params = [];
        $method = $cbData;
        if (preg_match('/^(.*):(.+)$/', $cbData, $matches)) {
            $method = $matches[1]; // e.g., 'completeMaterial'
            $params[] = $matches[2];
        }

        dump("Handling callback: $method with params: " . json_encode($params));

        $activeSceneHandler = $this->getSceneHandler();
        if ($activeSceneHandler && method_exists($activeSceneHandler, $method)) {
            call_user_func([$activeSceneHandler, $method], ...$params);
            return;
        }

        if (array_key_exists($method, $this->callbackHandlers)) {
            $this->dispatchCallback($method, $params);
            return;
        }

        $sceneHandler = $this->getSceneHandler($method);
        if ($sceneHandler) {
            $this->setScene($method);
            call_user_func([$sceneHandler, 'handleScene'], $params);
            return;
        }

        if (method_exists($this, $method)) {
            call_user_func([$this, $method], ...$params);
            return;
        }

        $this->tgBot->answerCbQuery(['text' => 'Invalid callback data.']);
    }

    public function handleText(string $text): void
    {
        $this->sendMainMenu();
    }

    public function backMainMenu(): void
    {
        $this->tgBot->answerCbQuery();
        $this->resetCache();
        $this->sendMainMenu(true);
    }

    protected function getSceneHandler($scene = null): ?SceneHandlerInterface
    {
        $scene = $scene ?: $this->getScene();
        if (!$scene) {
            return null;
        }

        $sceneClass = $this->sceneHandlers[$scene] ?? null;
        if (!$sceneClass || !class_exists($sceneClass)) {
            return null;
        }

        return new $sceneClass($this);
    }

    public function dispatchCallback(string $method, array $params = []): void
    {
        [$class, $action] = $this->callbackHandlers[$method];

        // Pass the bot handler as context
        $cbHandler = new $class($this);
        if (!method_exists($cbHandler, $action)) {
            throw new Exception("Method $action not found in class $class");
        }

        call_user_func_array([$cbHandler, $action], $params);
    }

    public function sendMainMenu($edit = false): void
    {
        $msg = self::getUserDetailsMsg($this->user);

        $params = [
            'chat_id' => $this->tgBot->chatId,
            'text' => <<<HTML
<b>Your details:</b>

$msg
HTML,
            'reply_markup' => $this->getMainKb(),
            'parse_mode' => 'HTML',
        ];

        if ($edit) {
            $params['message_id'] = $this->tgBot->getMessageId();
            $this->tgBot->sendRequestAsync('editMessageText', $params);
        } else {
            $this->tgBot->sendRequestAsync('sendMessage', $params);
        }
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

    public function resetCache(): void
    {
        $this->cache->forget($this->getCacheKey('state'));
        $this->cache->forget($this->getCacheKey('scene'));
    }
}
