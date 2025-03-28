<?php

namespace App\Services;

use App\Models\User;
use App\Services\Handler\HandlerFactory;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * @throws GuzzleException
     */
    public function sendRequest(string $method, array $params = []): array
    {
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

    public static function sendMessage(string $username, string $message, $params = []): void
    {
        // Send message to Telegram
        try {
            $resp = Http::post(
                'https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/sendMessage',
                array_merge([
                    'chat_id' => $username,
                    'text' => $message,
                ], $params)
            );
        } catch (\Throwable $e) {
            Log::channel('telegram')->error($e->getMessage());
        }
    }
}
