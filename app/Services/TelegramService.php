<?php

namespace App\Services;

use App\Models\User;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public static function getInlineKeyboard(array $data): array
    {
        return [
            'inline_keyboard' => $data,
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
        $token = config('services.telegram.bot_token');
        if (!$token) {
            throw new Exception('TELEGRAM_BOT_TOKEN is not set');
        }

        try {
            $resp = Http::timeout((int) env('TELEGRAM_HTTP_TIMEOUT', 10))
                ->retry(2, 200)
                ->post(
                'https://api.telegram.org/bot' . $token . '/' . $method,
                $params
            );
            return $resp->json() ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @throws Exception
     */
    public static function sendMessage(User $user, string $message, $params = []): ?array
    {
        /** @var User|null $fromUser */
        $fromUser = auth()->user();
        $chatId = $user->chat_id;

        try {
            $testChatId = config('services.telegram.test_chat_id');
            if ($testChatId) {
                $chatId = $testChatId; // Replace with your test chat ID
                if ($fromUser) {
                    $message .= "\n\n<code>Test mode: message sent from " . $fromUser->name . " to " . $user->name . "</code>";
                } else {
                    $message .= "\n\n<code>Test mode</code>";
                }
            }

            $resp = Http::timeout((int) env('TELEGRAM_HTTP_TIMEOUT', 10))
                ->retry(2, 200)
                ->post(
                'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage',
                array_merge([
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ], $params)
            );

            $data = $resp->json();
            if (Arr::get($data, 'ok') !== true) {
                throw new Exception('Telegram API error: ' . Arr::get($data, 'description'));
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('Failed to send Telegram message', [
//                'user_id' => $username,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
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
