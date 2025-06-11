<?php

namespace App\Listeners;

use App\Enums\RoleType;
use App\Enums\TaskAction;
use App\Events\SupplyOrderChanged;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\User;
use App\Services\TaskService;
use App\Services\TelegramService;
use App\Services\TgMessageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SupplyOrderNotification
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
    public function handle(SupplyOrderChanged $event): void
    {
        $supplyOrder = $event->supplyOrder;

        /** @var Collection<User> $supplyManagers */
        $supplyManagers = User::query()
            ->ownOrganization()
            ->exceptMe()
            ->whereIn('role', [RoleType::SENIOR_SUPPLY_MANAGER, RoleType::SUPPLY_MANAGER])
            ->get();

        if ($event->isNew) {
            $message = "<b>New Supply order created</b>\n\n";
        } else {
            $message = "<b>Supply order updated</b>\n\n";
        }
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

        foreach ($supplyManagers as $supplyManager) {
            try {
                TelegramService::sendMessage($supplyManager->chat_id, $message, [
                    'parse_mode' => 'HTML',
                    'reply_markup' => TelegramService::getInlineKeyboard([
                        [['text' => 'Confirm order', 'callback_data' => "confirmSupplyOrder:$supplyOrder->id"]]
                    ]),
                ]);
            } catch (\Throwable $e) {
                // Log the error or handle it as needed
                Log::error('Failed to send Telegram message', [
                    'user_id' => $supplyManager->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        TaskService::createTaskForRoles(
            toUserRoles: [
                RoleType::SUPPLY_MANAGER->value,
                RoleType::SENIOR_SUPPLY_MANAGER->value,
            ],
            relatedType: SupplyOrder::class,
            relatedId: $supplyOrder->id,
            action: TaskAction::Check,
            comment: 'Supply order ' . ($event->isNew ? 'created' : 'updated') . '.',
        );
    }
}
