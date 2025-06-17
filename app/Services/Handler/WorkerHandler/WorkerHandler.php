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

        if ($execution->declinedByProdManager) {
            $message = "<b>" . __('telegram.execution_approved_by', ['name' => $this->user->name]) . "</b>\n\n";
            $message .= TgMessageService::getExecutionMsg($execution);

            TelegramService::sendMessage($execution->declinedByProdManager, $message, [
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [
                        ['text' => __('telegram.decline'), 'callback_data' => "declineExecution:$execution->id"],
                        ['text' => __('telegram.approve'), 'callback_data' => "approveExecution:$execution->id"],
                    ]
                ])
            ]);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => "<b>" . __('telegram.execution_approved') . "</b>\n\n" . TgMessageService::getExecutionMsg($execution),
                'parse_mode' => 'HTML',
            ]);
        }
    }

    public function validateUser(User $user): bool
    {
        if (!$user->work_station_id) {
            $this->tgBot->answerMsg(['text' => __('telegram.not_assigned_to_workstation')]);
            return false;
        }

        return true;
    }

    public function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [['text' => __('telegram.add_execution'), 'callback_data' => 'createExecution']]
        ]);
    }
}
