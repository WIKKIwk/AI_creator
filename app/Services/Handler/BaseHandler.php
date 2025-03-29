<?php

namespace App\Services\Handler;

use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\TgBot\TgBot;
use Illuminate\Support\Arr;

class BaseHandler implements HandlerInterface
{
    use HandlerTrait;

    protected User $user;

    public function __construct(protected TgBot $tgBot, protected Cache $cache)
    {
    }

    public function validateUser(User $user): void
    {
        //
    }

    public function handle(User $user, array $update): void
    {
        $this->validateUser($user);

        $this->user = $user;
        $this->tgBot->setUpdate($update);

        $input = $this->tgBot->input;

        // 1️⃣ Handle commands
        if ($input === '/start') {
            $this->handleStart();
            return;
        }

        if ($input === '/help') {
            $this->handleHelp();
            return;
        }

        if ($cbData = Arr::get($update, 'callback_query.data')) {
            $this->handleCbQuery($cbData);
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
        //
    }

    public function handleHelp(): void
    {
        //
    }

    public function handleCbQuery(string $cbData): void
    {
        //
    }

    public function handleText(string $text): void
    {
        //
    }
}
