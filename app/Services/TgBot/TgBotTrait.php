<?php

namespace App\Services\TgBot;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Arr;

trait TgBotTrait
{
    public function getChatId()
    {
        return self::getChatIdByUpdate($this->update);  // If no chat_id found
    }

    public static function getChatIdByUpdate(array $update)
    {
        return Arr::get($update, 'message.chat.id') ??
            Arr::get($update, 'callback_query.from.id') ??
            Arr::get($update, 'callback_query.message.chat.id') ??
            Arr::get($update, 'edited_message.chat.id') ??
            Arr::get($update, 'inline_query.from.id') ??
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
