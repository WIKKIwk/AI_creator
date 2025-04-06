<?php

namespace App\Services\TgBot;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Arr;

trait TgBotTrait
{
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

    public function sendMsgAsync(array $params): PromiseInterface
    {
        if (Arr::get($params, 'message_id')) {
            return $this->sendRequestAsync('editMessageText', $params);
        }

        return $this->sendRequestAsync('sendMessage', $params);
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
    public function sendMsg(array $params): array
    {
        if (Arr::has($params, 'message_id')) {
            return $this->sendRequest('editMessageText', $params);
        }

        return $this->sendRequest('sendMessage', $params);
    }

    public function answerMsg(array $params): array
    {
        return $this->sendRequest('sendMessage', array_merge($params, [
            'chat_id' => $this->chatId,
        ]));
    }

    /**
     * @throws GuzzleException
     */
    public function answerCbQuery(array $params = []): array
    {
        return $this->sendRequest('answerCallbackQuery', array_merge([
            'callback_query_id' => Arr::get($this->update, 'callback_query.id')
        ], $params));
    }

    public function getChatId()
    {
        return Arr::get($this->update, 'message.chat.id') ??
            Arr::get($this->update, 'callback_query.message.chat.id') ??
            Arr::get($this->update, 'edited_message.chat.id') ??
            Arr::get($this->update, 'channel_post.chat.id');  // If no chat_id found
    }

    public static function getChatIdByUpdate(array $update)
    {
        return Arr::get($update, 'message.chat.id') ??
            Arr::get($update, 'callback_query.message.chat.id') ??
            Arr::get($update, 'edited_message.chat.id') ??
            Arr::get($update, 'channel_post.chat.id');  // If no chat_id found
    }

    public function getText()
    {
        return Arr::get($this->update, 'message.text') ??
            Arr::get($this->update, 'edited_message.text') ??
            Arr::get($this->update, 'channel_post.text');  // If no text found
    }

    public function getMessageId()
    {
        return Arr::get($this->update, 'message.message_id');
    }
}
