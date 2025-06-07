<?php

namespace App\Listeners;

use App\Enums\ProdOrderGroupType;
use App\Enums\RoleType;
use App\Enums\TaskAction;
use App\Events\ProdOrderChanged;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\User;
use App\Services\TaskService;
use App\Services\TelegramService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ProdOrderNotification
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
    public function handle(ProdOrderChanged $event): void
    {
        $poGroup = $event->poGroup;

        /** @var Collection<User> $PMs */
        $PMs = User::query()
            ->ownOrganization()
            ->exceptMe()
            ->where('role', RoleType::PRODUCTION_MANAGER)
            ->get();

        foreach ($PMs as $PM) {
            try {
                if ($event->isNew) {
                    $message = "<b>New Production Order Created</b>\n\n";
                } else {
                    $message = "<b>Production Order Updated</b>\n\n";
                }
                $message .= self::getProdOrderMsg($poGroup);

                TelegramService::sendMessage($PM->chat_id, $message, [
                    'parse_mode' => 'HTML',
                    'reply_markup' => TelegramService::getInlineKeyboard([
                        [['text' => 'Confirm order', 'callback_data' => "confirmProdOrder:$poGroup->id"]]
                    ]),
                ]);
            } catch (\Throwable $e) {
                // Log the error or handle it as needed
                Log::error('Failed to send Telegram message', [
                    'user_id' => $PM->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        TaskService::createTaskForRoles(
            toUserRoles: [
                RoleType::PRODUCTION_MANAGER->value
            ],
            relatedType: ProdOrderGroup::class,
            relatedId: $poGroup->id,
            action: TaskAction::Confirm,
            comment: 'New production order created. Please confirm the order.',
        );
    }

    public static function getProdOrderMsg(ProdOrderGroup $poGroup): string
    {
        $isConfirmed = $poGroup->isConfirmed() ? '✅' : '❌';
        $message = "Type: <b>{$poGroup->type->getLabel()}</b>\n";
        $message .= "Warehouse: <b>{$poGroup->warehouse->name}</b>\n";

        if ($poGroup->type == ProdOrderGroupType::ByOrder) {
            $message .= "Agent: <b>{$poGroup->organization->name}</b>\n";
        } else {
            $message .= "Deadline: <b>{$poGroup->deadline->format('d M Y')}</b>\n";
        }

        $message .= "Progress: <b>{$poGroup->getProgress()}%</b>\n";
        $message .= "Created by: <b>{$poGroup->createdBy->name}</b>\n";
        $message .= "Created at: <b>{$poGroup->created_at->format('d M Y H:i')}</b>\n";
        $message .= "Confirmed: $isConfirmed\n";

        $message .= "\nProducts:";
        foreach ($poGroup->prodOrders as $index => $prodOrder) {
            $index++;
            $isConfirmed = $prodOrder->isConfirmed() ? '✅' : '❌';

            $message .= "\n";
            $message .= "$index) Code: <b>$prodOrder->number</b>\n";
            $message .= "Product: <b>{$prodOrder->product->catName}</b>\n";
            $message .= "Quantity: <b>$prodOrder->quantity {$prodOrder->product->category->measure_unit->getLabel()}</b>\n";
            $message .= "Offer price: <b>$prodOrder->offer_price</b>\n";
            $message .= "Progress: <b>{$prodOrder->getProgress()}%</b>\n";
            $message .= "Expected cost: <b>$prodOrder->total_cost</b>\n";
            $message .= "Expected deadline: <b>$prodOrder->deadline days</b>\n";
            $message .= "Confirmed: $isConfirmed\n";
        }

        return $message;
    }
}
