<?php

namespace App\Listeners;

use App\Enums\ProdOrderGroupType;
use App\Enums\RoleType;
use App\Enums\TaskAction;
use App\Events\ProdOrderChanged;
use App\Events\SupplyOrderChanged;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\User;
use App\Services\TaskService;
use App\Services\TelegramService;
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

        foreach ($supplyManagers as $supplyManager) {
            try {
                if ($event->isNew) {
                    $message = "<b>New Supply order created</b>\n\n";
                } else {
                    $message = "<b>Supply order updated</b>\n\n";
                }
                $message .= self::getSupplyOrderMsg($supplyOrder);

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

    public static function getSupplyOrderMsg(SupplyOrder $supplyOrder): string
    {
        $isConfirmed = $supplyOrder->isConfirmed() ? '✅' : '❌';
        $message = "Code: <b>{$supplyOrder->number}</b>\n";
        $message .= "Warehouse: <b>{$supplyOrder->warehouse->name}</b>\n";
        $message .= "Category: <b>{$supplyOrder->productCategory->name}</b>\n";
        $message .= "Supplier: <b>{$supplyOrder->supplierOrganization?->name}</b>\n";
        $message .= "Status: <b>{$supplyOrder->getStatus()}</b>\n";
        $message .= "Created by: <b>{$supplyOrder->createdBy->name}</b>\n";
        $message .= "Created at: <b>{$supplyOrder->created_at->format('d M Y H:i')}</b>\n";
        $message .= "Confirmed: $isConfirmed\n";

        $message .= "\nProducts:";
        foreach ($supplyOrder->products as $index => $product) {
            $index++;
            $message .= "\n";
            $message .= "$index) Product: <b>{$product->product->catName}</b>\n";
            $message .= "Quantity: <b>$product->expected_quantity {$product->product->category->measure_unit->getLabel()}</b>\n";
            $message .= "Price: <b>$product->price</b>\n";
        }

        return $message;
    }
}
