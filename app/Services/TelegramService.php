<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\GuzzleException;

class TelegramService
{
    public static function getInlineKeyboard(array $data): array
    {
        return [
            'inline_keyboard' => $data,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];
    }

    public static function getKeyboard(array $array): array
    {
        return [
            'keyboard' => $array,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];
    }


    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function sendRequest(string $method, array $params = []): array
    {
        if (!env('TELEGRAM_BOT_TOKEN')) {
            throw new Exception('TELEGRAM_BOT_TOKEN is not set');
        }

        $client = new Client();

        $requestUrl = 'https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/' . $method;

        $response = $client->request('POST', $requestUrl, [
            'json' => $params
        ]);

        try {
            $response = $response->getBody()->getContents();
            return json_decode($response, true);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @throws Exception
     */
    public static function sendMessage(string $username, string $message, $params = []): ?array
    {
        $resp = Http::post(
            'https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/sendMessage',
            array_merge([
                'chat_id' => $username,
                'text' => $message,
            ], $params)
        );

        $data = $resp->json();
        if (Arr::get($data, 'ok') !== true) {
            throw new Exception('Telegram API error: ' . Arr::get($data, 'description'));
        }

        return $data;
    }

    public static function inlineResults($items, string $idKey, string $titleKey, string $prefixCommand = ''): array
    {
        $results = [];

        foreach ($items as $item) {
            $results[] = [
                'type' => 'article',
                'id' => (string) $item[$idKey],
                'title' => (string) $item[$titleKey],
                'input_message_content' => [
                    'message_text' => $prefixCommand . $item[$idKey],
                ],
                'description' => method_exists($item, 'getInlineDescription')
                    ? $item->getInlineDescription()
                    : '',
            ];
        }

        return $results;
    }

}
