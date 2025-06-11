<?php

namespace App\Listeners;

use App\Enums\RoleType;
use App\Enums\TaskAction;
use App\Events\StepExecutionCreated;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\User;
use App\Services\TaskService;
use App\Services\TelegramService;
use App\Services\TgMessageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class StepExecutionNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(StepExecutionCreated $event): void
    {
        $poStepExecution = $event->poStepExecution;

        /** @var Collection<User> $stockManagers */
        $stockManagers = User::query()
            ->ownOrganization()
            ->exceptMe()
            ->whereIn('role', [RoleType::STOCK_MANAGER, RoleType::SENIOR_STOCK_MANAGER])
            ->get();

        $message = "<b>New execution created</b>\n\n";
        $message .= TgMessageService::getExecutionMsg($poStepExecution);

        foreach ($stockManagers as $stockManager) {
            try {
                TelegramService::sendMessage($stockManager->chat_id, $message, [
                    'parse_mode' => 'HTML',
                    'reply_markup' => TelegramService::getInlineKeyboard([
                        [['text' => 'Approve', 'callback_data' => "approveExecution:$poStepExecution->id"]]
                    ]),
                ]);
            } catch (Throwable $e) {
                // Log the error or handle it as needed
                Log::error('Failed to send Telegram message', [
                    'user_id' => $stockManager->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        TaskService::createTaskForRoles(
            toUserRoles: [
                RoleType::STOCK_MANAGER->value,
                RoleType::SENIOR_STOCK_MANAGER->value
            ],
            relatedType: ProdOrderStepExecution::class,
            relatedId: $poStepExecution->id,
            action: TaskAction::Confirm,
            comment: 'New execution created for production order step',
        );
    }
}
