<?php

namespace App\Services\Handler\Ai;

use App\Services\AiContextService;
use App\Services\AiService;
use App\Services\Handler\BaseHandler;

class AiHandler extends BaseHandler
{
    protected const HISTORY_KEY = 'ai_history';

    public function handleStart(): void
    {
        $this->tgBot->answerMsg([
            'text' => "AI Assistantga xush kelibsiz. Savolingizni yozing. /cancel — suhbatni tozalash",
        ]);
    }

    public function handleText(string $text): void
    {
        if (in_array($text, ['/reset', '/cancel'])) {
            $this->forgetCache(self::HISTORY_KEY);
            $this->tgBot->answerMsg(['text' => 'Suhbat tozalandi. Yangi savol yuboring.']);
            return;
        }

        // Build history
        $history = $this->getCacheArray(self::HISTORY_KEY) ?? [];
        $history[] = ['role' => 'user', 'content' => $text];

        // Keep last 20 messages
        $history = array_slice($history, -20);

        /** @var AiContextService $ctx */
        $ctx = app(AiContextService::class);
        $context = $ctx->summary();

        /** @var AiService $ai */
        $ai = app(AiService::class);
        $answer = $ai->chat($this->user, $text, $history, $context);

        $history[] = ['role' => 'assistant', 'content' => $answer];
        $this->setCacheArray(self::HISTORY_KEY, $history);

        foreach ($this->chunkTelegram($answer) as $chunk) {
            $this->tgBot->answerMsg([
                'text' => $chunk,
                'parse_mode' => 'HTML',
            ], true);
        }
        $this->tgBot->settlePromises();
    }

    public function getMainKb(): array
    {
        return [
            'keyboard' => [
                [ ['text' => '📊 Ombor bo\'yicha savol berish'] ],
                [ ['text' => '/reset'] ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    private function chunkTelegram(string $text): array
    {
        // Telegram limit ~4096 chars.
        $max = 3500;
        $chunks = [];
        while (mb_strlen($text, 'UTF-8') > $max) {
            $chunks[] = mb_substr($text, 0, $max, 'UTF-8');
            $text = mb_substr($text, $max, null, 'UTF-8');
        }
        if ($text !== '') $chunks[] = $text;
        return $chunks;
    }
}

