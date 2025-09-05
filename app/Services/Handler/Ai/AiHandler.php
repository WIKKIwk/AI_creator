<?php

namespace App\Services\Handler\Ai;

use App\Services\AiContextService;
use App\Services\AiService;
use App\Services\Handler\BaseHandler;
use Illuminate\Support\Arr;

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

        // Show typing indicator and quick placeholder (like ChatGPT)
        try {
            $this->tgBot->sendRequest('sendChatAction', [
                'chat_id' => $this->tgBot->chatId,
                'action' => 'typing',
            ]);
        } catch (\Throwable $e) {
            // ignore chat action failures
        }

        $placeholder = $this->tgBot->answerMsg([
            'text' => "🧠 O'ylayapman...",
        ], false);
        $editMsgId = Arr::get($placeholder, 'result.message_id');
        if ($editMsgId) {
            $this->setCache('edit_msg_id', $editMsgId);
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

        // Escape HTML to avoid invalid tags in Telegram HTML mode
        $safeAnswer = htmlspecialchars($answer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $chunks = $this->chunkTelegram($safeAnswer);

        if ($editMsgId && !empty($chunks)) {
            // Edit the placeholder with the first chunk
            $first = array_shift($chunks);
            try {
                $this->tgBot->sendRequest('editMessageText', [
                    'chat_id' => $this->tgBot->chatId,
                    'message_id' => $editMsgId,
                    'text' => $first,
                    'parse_mode' => 'HTML',
                ]);
            } catch (\Throwable $e) {
                // Fallback: send as a new message if edit fails
                $this->tgBot->answerMsg([
                    'text' => $first,
                    'parse_mode' => 'HTML',
                ], true);
            }
        }

        // Send remaining chunks (if any)
        foreach ($chunks as $chunk) {
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
