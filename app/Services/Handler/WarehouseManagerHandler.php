<?php

namespace App\Services\Handler;

use Exception;
use App\Services\TgBot;
use Illuminate\Support\Arr;
use App\Services\Cache\Cache;
use App\Services\TelegramService;

class WarehouseManagerHandler implements HandlerInterface
{
    protected array $promises = [];

    public function __construct(
        protected TgBot $tgBot,
        protected Cache $cache,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(array $msg): void
    {
        $this->tgBot->sendRequestAsync('deleteMessage', [
            'chat_id' => Arr::get($msg, 'chat.id'),
            'message_id' => Arr::get($msg, 'message_id'),
        ]);

        $cbData = Arr::get($msg, 'data');
        dump($cbData);

        $this->tgBot->sendRequestAsync('sendMessage', [
            'chat_id' => Arr::get($msg, 'chat.id'),
            'text' => Arr::get($msg, 'text'),
            'reply_markup' => $this->getMainKb()
        ]);

        $this->tgBot->settlePromises();
    }

    protected function getProductKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [
                ['text' => 'ℹ️ Info', 'callback_data' => 'info']
            ],
            [
                ['text' => '📞 Contact Us', 'callback_data' => 'contact']
            ]
        ]);
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [
                ['text' => 'ℹ️ Info', 'callback_data' => 'info']
            ],
            [
                ['text' => '📞 Contact Us', 'callback_data' => 'contact']
            ]
        ]);
    }
}
