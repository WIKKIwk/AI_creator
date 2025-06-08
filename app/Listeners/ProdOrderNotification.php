<?php

namespace App\Listeners;

use App\Enums\ProdOrderGroupType;
use App\Enums\RoleType;
use App\Enums\TaskAction;
use App\Events\ProdOrderChanged;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\Product;
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

        if ($event->isNew) {
            $message = "<b>New ProdOrder created</b>\n\n";
        } else {
            $message = "<b>ProdOrder updated</b>\n\n";
        }
        $message .= self::getProdOrderGroupMsg($poGroup);

        foreach ($PMs as $PM) {
            try {
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

    public static function getProdOrderGroupMsg(ProdOrderGroup $poGroup): string
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
            $message .= "\n";
            $message .= self::getProdOrderMsg($prodOrder, $index);
        }

        return $message;
    }

    public static function getProdOrderMsg(ProdOrder $prodOrder, $index = null): string
    {
        $message = ($index ? "$index) " : '') . "Code: <b>$prodOrder->number</b>\n";
        $message .= "Product: <b>{$prodOrder->product->catName}</b>\n";
        $message .= "Quantity: <b>$prodOrder->quantity {$prodOrder->product->category->measure_unit->getLabel()}</b>\n";
        $message .= "Offer price: <b>$prodOrder->offer_price</b>\n";
        $message .= "Progress: <b>{$prodOrder->getProgress()}%</b>\n";
        $message .= "Expected cost: <b>$prodOrder->total_cost</b>\n";
        $message .= "Expected deadline: <b>$prodOrder->deadline days</b>\n";

        $confirmed = $prodOrder->isConfirmed() ? '✅' : '❌';
        $message .= "Confirmed: $confirmed\n";

        return $message;
    }

    public static function getPoStepMsg(ProdOrderStep $poStep): string
    {
        $measureUnit = $poStep->outputProduct->getMeasureUnit();

        $message = 'WorkStation: <b>' . $poStep->workStation->name . '</b>' . "\n";
        $message .= 'Status: <b>' . $poStep->status->getLabel() . '</b>' . "\n";
        $message .= 'Output product: <b>' . $poStep->outputProduct->catName . '</b>' . "\n";
        $message .= 'Expected quantity: <b>' . $poStep->expected_quantity . ' ' . $measureUnit->getLabel(
            ) . '</b>' . "\n";
        $message .= 'Output quantity: <b>' . $poStep->output_quantity . ' ' . $measureUnit->getLabel() . '</b>' . "\n";

        return $message;
    }

    public static function getMaterialMsg(ProdOrderStepProduct $material, $index = null): string
    {
        $measureUnit = $material->product->getMeasureUnit();

        $message = '';
        if (!$index) {
            $message .= "Prod order: <b>{$material->step->prodOrder->number}</b>\n";
            $message .= "Step: <b>{$material->step->workStation->name}</b>\n";
        }

        $message .= ($index ? "$index) ": '') . "Material: <b>{$material->product->catName}</b>\n";
        $message .= "Required: <b>$material->required_quantity {$measureUnit->getLabel()}</b>\n";
        $message .= "Available: <b>$material->available_quantity {$measureUnit->getLabel()}</b>\n";
        $message .= "Used: <b>$material->used_quantity {$measureUnit->getLabel()}</b>\n";

        return $message;
    }

    public static function getExecutionMsg(ProdOrderStepExecution $execution): string
    {
        $message = "Prod order: <b>{$execution->prodOrderStep->prodOrder->number}</b>\n";
        $message .= "Step: <b>{$execution->prodOrderStep->workStation->name}</b>\n";
        $message .= "Executed by: <b>{$execution->executedBy->name}</b>\n";
        $message .= "Executed at: <b>{$execution->created_at->format('d M Y H:i')}</b>\n";
        $message .= "Output product: <b>{$execution->prodOrderStep->outputProduct->catName}</b>\n";
        $message .= "Output quantity: <b>$execution->output_quantity {$execution->prodOrderStep->outputProduct->getMeasureUnit()->getLabel()}</b>\n";
        $message .= "Notes: <i>$execution->notes</i>\n";

        if (!empty($execution->materials)) {
            $message .= "\n<b>Used materials:</b>\n";
            foreach ($execution->materials as $index => $material) {
                $index++;
                $message .= "$index) <b>{$material->product->catName}</b>: $material->used_quantity {$material->product->getMeasureUnit()->getLabel()}\n";
            }
        }

        return $message;
    }
}
