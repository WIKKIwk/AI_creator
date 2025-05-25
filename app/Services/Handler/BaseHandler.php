<?php

namespace App\Services\Handler;

use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\TgBot\TgBot;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;

class BaseHandler implements HandlerInterface
{
    use HandlerTrait;

    protected User $user;

    public function __construct(protected TgBot $tgBot, protected Cache $cache)
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
        if (preg_match('/^(.*)_(\d+)$/', $cbData, $matches)) {
            $base = $matches[1];   // e.g., 'completeMaterial'
            $id = (int)$matches[2]; // e.g., 98

            $callback = $base . 'Callback';
            if (method_exists($this, $callback)) {
                call_user_func([$this, $callback], $id);
            } else {
                throw new Exception("Method '$callback' does not exist.");
            }

            return;
        }


        if (method_exists($this, $cbData)) {
            call_user_func([$this, $cbData]);
        } else {
            $this->tgBot->answerCbQuery(['text' => "Invalid callback data."]);
        }
    }

    public function handleText(string $text): void
    {
        //
    }
}
