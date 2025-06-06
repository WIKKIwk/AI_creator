<?php

namespace App\Listeners;

use App\Enums\ProdOrderGroupType;
use App\Enums\RoleType;
use App\Enums\TaskAction;
use App\Events\StepExecutionCreated;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\User;
use App\Services\TaskService;
use App\Services\TelegramService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

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
            ->where('role', RoleType::PRODUCTION_MANAGER)
            ->whereNot('id', auth()->user()->id)
            ->get();

        foreach ($stockManagers as $stockManager) {
            try {
                $message = "<b>New execution created</b>\n\n";
                $message .= self::getExecutionMsg($poStepExecution);

                TelegramService::sendMessage($stockManager->chat_id, $message, [
                    'parse_mode' => 'HTML',
                    'reply_markup' => TelegramService::getInlineKeyboard([
                        [['text' => 'Approve', 'callback_data' => "approveExecution:$poStepExecution->id"]]
                    ]),
                ]);
            } catch (\Throwable $e) {
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

    public static function getExecutionMsg(ProdOrderStepExecution $poStepExecution): string
    {
        $message = "Prod order: <b>{$poStepExecution->prodOrderStep->prodOrder->number}</b>\n";
        $message .= "Step: <b>{$poStepExecution->prodOrderStep->workStation->name}</b>\n";
        $message .= "Output product: <b>{$poStepExecution->prodOrderStep->product->catName}</b>\n";
        $message .= "Created by: <b>{$poStepExecution->executedBy->name}</b>\n";
        $message .= "Created at: <b>{$poStepExecution->created_at->format('d M Y H:i')}</b>\n";

        $message .= "\nUsed materials:";
        foreach ($poStepExecution->materials as $index => $material) {
            $index++;
            $message .= "\n";
            $message .= "$index) Product: <b>{$material->product->name}</b>\n";
            $message .= "Quantity: <b>$material->used_quantity {$material->product->category->measure_unit->getLabel()}</b>\n";
        }

        return $message;
    }
}
