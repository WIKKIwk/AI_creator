<?php

namespace App\Services\Handler\SupplyManager;

use App\Models\OrganizationPartner;
use App\Models\SupplyOrder\SupplyOrder;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\SupplyOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class SelectSupplierScene implements SceneHandlerInterface
{
    public TgBot $tgBot;
    public Cache $cache;

    public SupplyOrderService $supplyOrderService;

    public const states = [
        'selectSupplier' => 'selectSupplier',
    ];

    public function __construct(public SupplyManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->supplyOrderService = app(SupplyOrderService::class);
    }

    public function handleScene(array $params = []): void
    {
        $supplyOrderId = $params[0] ?? null;
        if (!$supplyOrderId) {
            return;
        }

        /** @var SupplyOrder $supplyOrder */
        $supplyOrder = SupplyOrder::query()->findOrFail($supplyOrderId);

        $form = $this->handler->getCacheArray('changeStatusSupplyForm') ?? [];
        $form['supply_order_id'] = $supplyOrder->id;
        $this->handler->setCacheArray('changeStatusSupplyForm', $form);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());

        $message = "<b>" . __('telegram.supplyorder_details') . "</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

        /** @var Collection<OrganizationPartner> $suppliers */
        $suppliers = OrganizationPartner::query()
            ->with('partner')
            ->supplier()
            ->get();

        $buttons = [];
        foreach ($suppliers as $supplier) {
            $buttons[] = [['text' => $supplier->partner->name, 'callback_data' => "setSupplier:$supplier->id"]];
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                ...$buttons,
                [['text' => __('telegram.cancel'), 'callback_data' => 'cancelSupplier']],
            ]),
        ]);
    }

    public function setSupplier($supplierId): void
    {
        $supplyOrder = $this->getSupplyOrder();

        try {
            $supplyOrder->supplier_id = $supplierId;
            $supplyOrder->saveOrFail();

            $this->tgBot->answerCbQuery(['text' => __('telegram.supplier_selected')], true);

            $message = "<b>" . __('telegram.supplier_selected') . "</b>\n\n";
            $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard($this->handler->getSupplyOrderButtons($supplyOrder)),
            ]);
        } catch (Throwable $e) {
            $this->tgBot->answerCbQuery(['text' => __('telegram.error_occurred')], true);
            $this->handler->resetCache();

            $message = "<b>" . __('telegram.error_occurred') . ":</b> {$e->getMessage()}\n\n";
            $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard($this->handler->getSupplyOrderButtons($supplyOrder)),
            ]);
        }
    }

    public function cancelSupplier(): void
    {
        $this->handler->resetCache();
        $this->tgBot->answerCbQuery(['text' => __('telegram.operation_cancelled')], true);

        $supplyOrder = $this->getSupplyOrder();

        $message = "<b>" . __('telegram.supplyorder_details') . "</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard($this->handler->getSupplyOrderButtons($supplyOrder)),
        ]);
    }

    public function getSupplyOrder(): SupplyOrder
    {
        $form = $this->handler->getCacheArray('changeStatusSupplyForm');
        $id = $form['supply_order_id'];

        /** @var SupplyOrder $supplyOrder */
        $supplyOrder = SupplyOrder::query()->findOrFail($id);
        return $supplyOrder;
    }
}
