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

    /**
     * @throws GuzzleException
     */
    public function sendRequest(string $method, array $params): array
    {
        $res = $this->client->request('POST', $method, [
            'json' => $params
        ]);
        return json_decode($res->getBody()->getContents(), true);
    }

    public function sendRequestAsync(string $method, array $params): PromiseInterface
    {
        $promise = $this->client->requestAsync('POST', $method, [
            'json' => $params
        ]);
        $this->promises[$method] = $promise;
        return $promise;
    }

    public function settlePromises(): array
    {
        $responses = Utils::settle($this->promises)->wait();

        $result = [];
        foreach ($responses as $method => $response) {
            if ($response['state'] === 'fulfilled') {
                $result[$method] = json_decode($response['value']->getBody()->getContents(), true);
            }
        }

        return $result;
    }

    /**
     * @throws GuzzleException
     */
    public function sendMsg(array $params, bool $async = false): mixed
    {
        $method = $async ? 'sendRequestAsync' : 'sendRequest';
        if (Arr::has($params, 'message_id')) {
            return $this->{$method}('editMessageText', $params);
        }

        return $this->{$method}('sendMessage', $params);
    }

    public function answerMsg(array $params, bool $async = false): mixed
    {
        $method = $async ? 'sendRequestAsync' : 'sendRequest';
        return $this->{$method}('sendMessage', array_merge($params, [
            'chat_id' => $this->chatId,
        ]));
    }

    public function answerCbQuery(array $params = [], bool $async = false)
    {
        $cbQueryId = Arr::get($this->update, 'callback_query.id');
        if (!$cbQueryId) {
            return;
        }

        $method = $async ? 'sendRequestAsync' : 'sendRequest';
        return $this->{$method}('answerCallbackQuery', array_merge([
            'callback_query_id' => $cbQueryId
        ], $params));
    }

    public function rmLastMsg(): void
    {
        $this->sendRequestAsync('deleteMessage', [
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId,
        ]);
    }

    public function answerInlineQuery(array $params): void
    {
        $this->sendRequestAsync('answerInlineQuery', $params);
    }
}
