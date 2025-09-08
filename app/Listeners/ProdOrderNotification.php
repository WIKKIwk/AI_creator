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
use App\Models\ProdTemplate\ProdTemplate;
use App\Models\Product;
use App\Models\User;
use App\Services\TaskService;
use App\Services\TelegramService;
use App\Services\TgMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProdOrderNotification implements ShouldQueue
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
        $message .= TgMessageService::getProdOrderGroupMsg($poGroup);

        foreach ($PMs as $PM) {
            TelegramService::sendMessage($PM, $message, [
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => 'Confirm order', 'callback_data' => "confirmProdOrder:$poGroup->id"]]
                ]),
            ]);
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
}
