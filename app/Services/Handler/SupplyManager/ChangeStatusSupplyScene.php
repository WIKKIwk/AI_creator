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

        $form = $this->handler->getCacheArray('changeStatusSupplyForm') ?? [];
        $form['supply_order_id'] = $supplyOrderId;
        $this->handler->setCacheArray('changeStatusSupplyForm', $form);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());

        $this->editForm('Select new <b>State</b> for this Supply Order:', [
            ...collect(SupplyOrderState::cases())->map(fn($case) => [
                ['text' => $case->name, 'callback_data' => "setState:$case->value"]
            ])->toArray(),
            [['text' => '🚫 Cancel', 'callback_data' => 'cancelMoveStatus']]
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
        $this->editForm('✍️ Enter custom status text:', [
            [['text' => '🚫 Cancel', 'callback_data' => 'cancelMoveStatus']]
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

        $this->handler->setState(self::states['select_status']);
        $this->editForm('Select <b>Status</b> for state <b>' . $state?->getLabel() . '</b>:', [
            ...collect(SupplyOrderStatus::cases())->map(fn($case) => [
                ['text' => $case->getLabel(), 'callback_data' => "setStatus:$case->value"]
            ])->toArray(),
            [['text' => '✏️ Custom status', 'callback_data' => 'customStatus']],
            [['text' => '🚫 Cancel', 'callback_data' => 'cancelMoveStatus']]
        ]);
    }

    public function setStatus(string $status): void
    {
        $form = $this->handler->getCacheArray('changeStatusSupplyForm') ?? [];
        $form['selected_status'] = $status;
        $this->handler->setCacheArray('changeStatusSupplyForm', $form);

        $state = $form['selected_state'];

        $msg = "✅ Status selected.\n\n<b>State:</b> {$state}\n<b>Status:</b> {$status}";

        $this->editForm($msg, [
            [['text' => '🚫 Cancel', 'callback_data' => 'cancelMoveStatus']],
            [['text' => '✅ Save', 'callback_data' => 'saveFinalStatus']],
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

        $this->tgBot->answerCbQuery(['text' => '✅ Status updated successfully.'], true);

        $message = "<b>✅ Status updated successfully.</b>\n\n";
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
        $this->tgBot->answerCbQuery(['text' => '❌ Status change canceled.'], true);

        $supplyOrder = $this->getSupplyOrder();

        $message = "<b>✅ Status updated successfully.</b>\n\n";
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
