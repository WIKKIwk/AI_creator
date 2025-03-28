<?php

namespace App\Services\Handler;

use App\Services\BotService;
use App\Services\TelegramService;
use Illuminate\Support\Arr;

class WarehouseManagerHandler implements HandlerInterface
{
    public function __construct(
        protected TelegramService $telegramService
    ) {
    }

    public function handle(array $msg): void
    {
        $this->handleBasicCommands($msg);

        $this->telegramService->sendMessage(Arr::get($msg, 'chat.id'), 'Hello, Warehouse Manager!');
    }

    private function handleBasicCommands(array $msg): void
    {
        $text = Arr::get($msg, 'text');

        if ($text === '/start') {
            $this->telegramService->sendMessage(Arr::get($msg, 'chat.id'), 'Hello, Warehouse Manager!');
        }
    }
}
