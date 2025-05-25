<?php

namespace App\Services\Handler;

trait HandlerTrait
{
    protected function getCacheKey(string $key): string
    {
        return self::getCacheKeyByChatId($this->user->chat_id, $key);
    }

    public static function getCacheKeyByChatId($chatId, string $key): string
    {
        return $chatId . ':' . $key;
    }

    protected function parseUserInput($text): ?array
    {
        $pattern = '/^(\d+)\s+(.+)$/';

        if (preg_match($pattern, $text, $matches)) {
            return [
                'index' => (int)$matches[1],  // Extracted field index
                'value' => trim($matches[2]),  // Extracted text value
            ];
        }

        return null; // Invalid format
    }
}
