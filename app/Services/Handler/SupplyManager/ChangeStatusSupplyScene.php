<?php

namespace App\Services\Handler\SupplyManager;

use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Models\SupplyOrder\SupplyOrder;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\SupplyOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;

class ChangeStatusSupplyScene implements SceneHandlerInterface
{
    public TgBot $tgBot;
    public Cache $cache;

    public SupplyOrderService $supplyOrderService;

    public const states = [
        'await_custom_status' => 'await_custom_status',
        'select_status' => 'select_status',
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
        if ($supplyOrder->status == SupplyOrderStatus::AwaitingWarehouseApproval->value) {
            $this->handler->resetCache();
            $this->tgBot->answerCbQuery(['text' => __('telegram.waiting_for_warehouse_approval')], true);
            return;
        }

        $form = $this->handler->getCacheArray('changeStatusSupplyForm') ?? [];
        $form['supply_order_id'] = $supplyOrderId;
        $this->handler->setCacheArray('changeStatusSupplyForm', $form);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());

        $this->editForm(__('telegram.select_new_state_for_supply_order'), [
            ...collect([
                SupplyOrderState::InProgress,
                SupplyOrderState::Delivered,
            ])
                ->map(fn($case) => [['text' => $case->name, 'callback_data' => "setState:$case->value"]])
                ->toArray(),
            [['text' => __('telegram.cancel'), 'callback_data' => 'cancelMoveStatus']]
        ]);
    }

    public function handleText(string $text): void
    {
        if ($this->handler->getState() === self::states['await_custom_status']) {
            dump('await_custom_status');
            $this->setStatus($text);
        }
    }

    public function customStatus(): void
    {
        $this->handler->setState(self::states['await_custom_status']);
        $this->editForm(__('telegram.enter_custom_status_text'), [
            [['text' => __('telegram.cancel'), 'callback_data' => 'cancelMoveStatus']]
        ]);
    }

    public function setState($state): void
    {
        dump($state);
        $state = SupplyOrderState::tryFrom($state);
        dump($state);

        $form = $this->handler->getCacheArray('changeStatusSupplyForm') ?? [];
        $form['selected_state'] = $state?->getLabel();
        $form['selected_state_value'] = $state?->value;
        $this->handler->setCacheArray('changeStatusSupplyForm', $form);

        $statuses = [SupplyOrderStatus::SupplyDep];
        if ($state === SupplyOrderState::Delivered) {
            $statuses[] = SupplyOrderStatus::AwaitingWarehouseApproval;
        }

        $this->handler->setState(self::states['select_status']);
        $this->editForm(__('telegram.select_status_for_state', ['state' => $state?->getLabel()]), [
            ...collect($statuses)->map(fn($case) => [
                ['text' => $case->getLabel(), 'callback_data' => "setStatus:$case->value"]
            ])->toArray(),
            [['text' => __('telegram.custom_status'), 'callback_data' => 'customStatus']],
            [['text' => __('telegram.cancel'), 'callback_data' => 'cancelMoveStatus']]
        ]);
    }

    public function setStatus(string $status): void
    {
        $form = $this->handler->getCacheArray('changeStatusSupplyForm') ?? [];
        $form['selected_status'] = $status;
        $this->handler->setCacheArray('changeStatusSupplyForm', $form);

        $state = $form['selected_state'];
        $status = SupplyOrderStatus::tryFrom($status)?->getLabel() ?? $status;

        $msg = __("telegram.status_selected", ['state' => $state, 'status' => $status]);

        $this->editForm($msg, [
            [
                ['text' => __('telegram.cancel'), 'callback_data' => 'cancelMoveStatus'],
                ['text' => __('telegram.save'), 'callback_data' => 'saveFinalStatus']
            ],
        ]);
    }

    public function saveFinalStatus(): void
    {
        $form = $this->handler->getCacheArray('changeStatusSupplyForm');
        $state = $form['selected_state_value'];
        $status = $form['selected_status'];

        $supplyOrder = $this->getSupplyOrder();
        $attributes = $supplyOrder->changeStatus($state, $status);
        $supplyOrder->update($attributes);

        $this->handler->resetCache();

        $this->tgBot->answerCbQuery(['text' => __('telegram.status_updated_success')], true);

        $message = "<b>" . __('telegram.status_updated_success') . "</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard($this->handler->getSupplyOrderButtons($supplyOrder)),
        ]);
    }

    public function cancelMoveStatus(): void
    {
        $this->handler->resetCache();
        $this->tgBot->answerCbQuery(['text' => __('telegram.status_change_canceled')], true);

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

    public function editForm(string $text, array $markup = []): void
    {
        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard($markup),
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
