<?php

namespace App\Services\Handler\Codex;

use App\Services\Handler\BaseHandler;
use Illuminate\Support\Arr;

class CodexHandler extends BaseHandler
{
    public function handleStart(): void
    {
        $this->tgBot->answerMsg([
            'text' => "Codex rejimi. O'zgartirish instruktsiyasini yuboring.\n" .
                "Masalan: 'OrdersController performance optimize qil'.\n" .
                "Codex avval taklif (propose) qiladi: o'zgaradigan fayllar ro'yxati va qisqa sharh.\n" .
                "Siz '✅ Tasdiqlash' tugmasi bilan ruxsat bersangizgina u o'zgarishlarni qo'llaydi.",
        ]);
    }

    public function handleText(string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            $this->tgBot->answerMsg(['text' => 'Bo\'sh matn. Qayta yuboring.']);
            return;
        }
        $this->proposeAndAsk($text);
    }

    public function handleCbQuery(string $cbData): void
    {
        if (str_starts_with($cbData, 'codex_apply:')) {
            $id = substr($cbData, strlen('codex_apply:'));
            $this->dispatchApply($id);
            return;
        }
        if ($cbData === 'codex_cancel') {
            $this->forgetCache('codex_last_prompt');
            $this->tgBot->answerMsg(['text' => 'Bekor qilindi.']);
            return;
        }
        parent::handleCbQuery($cbData);
    }

    private function proposeAndAsk(string $text): void
    {
        $url = config('services.codex.url', 'http://codex:8090') . '/propose';
        $secret = config('services.codex.secret');

        try {
            // show typing and placeholder
            try {
                $this->tgBot->sendRequest('sendChatAction', [
                    'chat_id' => $this->tgBot->chatId,
                    'action' => 'typing',
                ]);
            } catch (\Throwable $e) {}

            $placeholder = $this->tgBot->answerMsg(['text' => "🧠 O'ylayapman..."], false);
            $editMsgId = Arr::get($placeholder, 'result.message_id');
            $resp = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Codex-Token' => (string) $secret,
            ])->timeout(20)->post($url, [
                'prompt' => $text,
            ]);
            if ((int) $resp->status() !== 200) {
                if ($editMsgId) {
                    $this->tgBot->sendRequest('editMessageText', [
                        'chat_id' => $this->tgBot->chatId,
                        'message_id' => $editMsgId,
                        'text' => 'Propose muvaffaqiyatsiz (HTTP ' . $resp->status() . ').',
                    ]);
                } else {
                    $this->tgBot->answerMsg(['text' => 'Propose muvaffaqiyatsiz (HTTP ' . $resp->status() . ').']);
                }
                return;
            }
            $meta = $resp->json();
            $files = $meta['files'] ?? [];
            $title = $meta['title'] ?? 'Taklif';
            $summary = $meta['summary'] ?? '';
            $id = $meta['id'] ?? '';

            $list = '';
            foreach (array_slice($files, 0, 10) as $f) {
                $list .= "\n• " . $f;
            }
            if (count($files) > 10) {
                $list .= "\n… va yana " . (count($files) - 10) . " ta fayl";
            }

            $textOut = "<b>" . htmlspecialchars($title) . "</b>\n" .
                htmlspecialchars($summary) . "\n\n<b>Fayllar:</b>" . htmlspecialchars($list);

            $params = [
                'chat_id' => $this->tgBot->chatId,
                'text' => $textOut,
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Tasdiqlash', 'callback_data' => 'codex_apply:' . $id],
                            ['text' => '❌ Bekor', 'callback_data' => 'codex_cancel'],
                        ],
                    ],
                ],
            ];
            if ($editMsgId) {
                $params['message_id'] = $editMsgId;
                $this->tgBot->sendRequest('editMessageText', $params);
            } else {
                $this->tgBot->answerMsg($params);
            }
        } catch (\Throwable $e) {
            $this->tgBot->answerMsg(['text' => 'Codex propose xatolik: ' . $e->getMessage()]);
        }
    }

    private function dispatchApply(string $id): void
    {
        $url = config('services.codex.url', 'http://codex:8090') . '/apply';
        $secret = config('services.codex.secret');
        try {
            $resp = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Codex-Token' => (string) $secret,
            ])->timeout(10)->post($url, [
                'id' => $id,
            ]);
            if ((int) $resp->status() === 202) {
                $this->tgBot->answerMsg(['text' => '✅ Tasdiq qabul qilindi. Codex o\'zgarishlarni qo\'llamoqda.']);
            } else {
                $this->tgBot->answerMsg(['text' => 'Qo\'llash muvaffaqiyatsiz (HTTP ' . $resp->status() . ').']);
            }
        } catch (\Throwable $e) {
            $this->tgBot->answerMsg(['text' => 'Codex apply xatolik: ' . $e->getMessage()]);
        }
    }

    public function getMainKb(): array
    {
        return [
            'keyboard' => [
                [ ['text' => '/cancel'] ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }
}
