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
    public const actions = [
        'approved_prod_senior_manager' => 'approved_prod_senior_manager',
        'approved_prod_manager' => 'approved_prod_manager',
        'approved_stock_manager' => 'approved_stock_manager',
    ];

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
        $action = $event->action;
        $poStepExecution = $event->poStepExecution;

        $roles = match ($action) {
            'approved_prod_senior_manager' => [RoleType::STOCK_MANAGER->value, RoleType::SENIOR_STOCK_MANAGER->value],
            'approved_prod_manager' => [RoleType::SENIOR_PRODUCTION_MANAGER->value],
            default => [RoleType::PRODUCTION_MANAGER->value],
        };

        /** @var Collection<User> $users */
        $users = User::query()
            ->ownOrganization()
            ->exceptMe()
            ->whereIn('role', $roles)
            ->get();

        $message = "<b>Execution created</b>\n\n";
        $message .= TgMessageService::getExecutionMsg($poStepExecution);
        $message .= "\n\nto " .  implode(',', $roles) . "\n";

        foreach ($users as $user) {
            TelegramService::sendMessage($user->chat_id, $message, [
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => 'Approve', 'callback_data' => "approveExecution:$poStepExecution->id"]]
                ]),
            ]);
        }

        TaskService::createTaskForRoles(
            toUserRoles: $roles,
            relatedType: ProdOrderStepExecution::class,
            relatedId: $poStepExecution->id,
            action: TaskAction::Approve,
            comment: 'Execution process. Need to approve',
        );
    }
}
