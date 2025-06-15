<?php

namespace App\Services\Handler\WorkerHandler;

use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\User;
use App\Services\Handler\BaseHandler;
use App\Services\TelegramService;
use App\Services\TgMessageService;

class WorkerHandler extends BaseHandler
{
    protected array $sceneHandlers = [
        'createExecution' => CreateExecutionScene::class,
    ];

    public const templates = [
        'createExecution' => <<<HTML
Current production order: <b>{prodOrder}</b>
Production product: <b>{product}</b>
Progress: <b>{progress}</b>

Expected output product: <b>{expectedMaterial}</b>
Produced output product: <b>{producedMaterial}</b>

Using materials:
{usingMaterials}
Choose the material to execute:
HTML,

        'createExecutionForm' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
    ];

    public function approveExecution($executionId): void
    {
        $this->tgBot->answerCbQuery();
        /** @var ProdOrderStepExecution $execution */
        $execution = ProdOrderStepExecution::query()->findOrFail($executionId);

        if ($execution->declinedBy) {
            $message = "<b>Execution approved by {$this->user->name}</b>\n\n";
            $message .= TgMessageService::getExecutionMsg($execution);

            if (env('TELEGRAM_TEST_CHAT_ID')) {
                $message .= "\n\nto <b>{$execution->declinedBy->name}</b>:\n";
            }

            TelegramService::sendMessage($execution->declinedBy->chat_id, $message, [
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [
                        ['text' => '❌ Decline', 'callback_data' => "declineExecution:$execution->id"],
                        ['text' => '✅ Approve', 'callback_data' => "approveExecution:$execution->id"]
                    ]
                ])
            ]);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => "<b>Execution approved</b>\n\n" . TgMessageService::getExecutionMsg($execution),
                'parse_mode' => 'HTML',
            ]);
        }
    }

    public function validateUser(User $user): bool
    {
        if (!$user->work_station_id) {
            $this->tgBot->answerMsg(['text' => "You're not assigned to any WorkStation. Please contact your manager."]);
            return false;
        }

        return true;
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [['text' => '🛠 Add execution', 'callback_data' => 'createExecution']]
        ]);
    }
}
