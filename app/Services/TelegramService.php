<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
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
