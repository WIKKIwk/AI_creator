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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class StepExecutionNotification implements ShouldQueue
{
    use InteractsWithQueue;
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
        $execution = $event->poStepExecution;

        /** @var Collection<User> $users */
        $users = User::query()
            ->ownOrganization()
            ->exceptMe()
            ->whereHas('workStations', function ($query) use ($execution) {
                $query->where('id', $execution->prodOrderStep->work_station_id);
            })
            ->whereIn('role', [RoleType::PRODUCTION_MANAGER->value])
            ->get();

        $message = "<b>Execution created</b>\n\n";
        $message .= TgMessageService::getExecutionMsg($execution);

        foreach ($users as $user) {
            TelegramService::sendMessage($user, $message, [
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [
                        ['text' => '❌ Decline', 'callback_data' => "declineExecution:$execution->id"],
                        ['text' => '✅ Approve', 'callback_data' => "approveExecution:$execution->id"]
                    ]
                ]),
            ]);
        }

        TaskService::createTaskForRoles(
            toUserRoles: [RoleType::PRODUCTION_MANAGER->value],
            relatedType: ProdOrderStepExecution::class,
            relatedId: $execution->id,
            action: TaskAction::Approve,
            comment: 'Execution process. Need to approve',
        );
    }
}
