<?php

namespace App\Services\TgBot;

use App\Services\TelegramService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Arr;

class TgBot
{
    use TgBotTrait;

    protected Client $client;
    protected array $promises = [];
    public array $update;
    public mixed $chatId;
    public mixed $input;
    public mixed $messageId;

    public function __construct(
        protected TelegramService $telegramService
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/',
        ]);
    }

    public function setUpdate(array $update): void
    {
        $this->update = $update;
        $this->chatId = $this->getChatId();
        $this->input = $this->getText();
        $this->messageId = $this->getMessageId();
    }

    public function rmLastMsg(): void
    {
        $this->sendRequestAsync('deleteMessage', [
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId,
        ]);
    }
}
