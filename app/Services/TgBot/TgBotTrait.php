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
    public function sendMsg(array $params, bool $async = false): array
    {
        $method = $async ? 'sendRequestAsync' : 'sendRequest';
        if (Arr::has($params, 'message_id')) {
            return $this->{$method}('editMessageText', $params);
        }

        return $this->{$method}('sendMessage', $params);
    }

    public function answerMsg(array $params, bool $async = false): array
    {
        $method = $async ? 'sendRequestAsync' : 'sendRequest';
        return $this->{$method}('sendMessage', array_merge($params, [
            'chat_id' => $this->chatId,
        ]));
    }

    /**
     * @throws GuzzleException
     */
    public function answerCbQuery(array $params = [], bool $async = false)
    {
        $method = $async ? 'sendRequestAsync' : 'sendRequest';
        return $this->{$method}('answerCallbackQuery', array_merge([
            'callback_query_id' => Arr::get($this->update, 'callback_query.id')
        ], $params));
    }

    public function getChatId()
    {
        return self::getChatIdByUpdate($this->update);  // If no chat_id found
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

    public static function getTextByUpdate(array $update)
    {
        return Arr::get($update, 'message.text') ??
            Arr::get($update, 'edited_message.text') ??
            Arr::get($update, 'channel_post.text');  // If no text found
    }

    public function getMessageId()
    {
        return self::getMessageIdUpdate($this->update);  // If no chat_id found
    }

    public static function getMessageIdUpdate(array $update)
    {
        return Arr::get($update, 'message.message_id') ??
            Arr::get($update, 'callback_query.message.message_id') ??
            Arr::get($update, 'edited_message.message_id');  // If no chat_id found
    }
}
