<?php

namespace App\Services\Handler\SupplyManager;

use App\Models\SupplyOrder\SupplyOrder;
use App\Models\User;
use App\Services\Handler\BaseHandler;
use App\Services\TelegramService;
use App\Services\TgMessageService;

class SupplyManagerHandler extends BaseHandler
{
    public User $user;
    protected array $promises = [];

    protected array $sceneHandlers = [
        'createSupplyOrder' => CreateSupplyOrderScene::class,
        'changeSupplyOrderStatus' => ChangeStatusSupplyScene::class,
    ];

    protected array $callbackHandlers = [
        'confirmSupplyOrder' => [CreateSupplyOrderScene::class, 'confirmSupplyOrder'],
        'closeSupplyOrder' => [CreateSupplyOrderScene::class, 'closeSupplyOrder'],
        'cancelMoveStatus' => [ChangeStatusSupplyScene::class, 'cancelMoveStatus'],

        'confirmListOrder' => [SupplyOrderListCb::class, 'confirmOrder'],
        'supplyOrdersList' => [SupplyOrderListCb::class, 'sendList'],
        'supplyOrderPrev' => [SupplyOrderListCb::class, 'prev'],
        'supplyOrderNext' => [SupplyOrderListCb::class, 'next'],
    ];

    public const templates = [
        'supplyOrderForm' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
    ];

    public function handleText(string $text): void
    {
        $activeState = $this->getState();

        if (str_starts_with($text, '/select_supply_order')) {
            $orderId = trim(str_replace('/select_supply_order ', '', $text));
            $this->selectSupplyOrder($orderId);
            return;
        }

        if ($activeState || $this->getScene()) {
            $this->tgBot->rmLastMsg();
            return;
        }

        $this->sendMainMenu();
    }

    public function selectSupplyOrder($orderId): void
    {
        dump("Selecting SupplyOrder: $orderId");
        $this->tgBot->answerCbQuery();
        /** @var SupplyOrder $supplyOrder */
        $supplyOrder = SupplyOrder::query()->findOrFail($orderId);

        $message = "<b>SupplyOrder details:</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

        $messageId = $this->getCache('edit_msg_id');

        $this->tgBot->sendRequestAsync($messageId ? 'editMessageText' : 'sendMessage', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard($this->getSupplyOrderButtons($supplyOrder)),
        ]);
    }

    public function changeSupplyOrderStatus(SupplyOrder $supplyOrder): void
    {
        $this->tgBot->answerCbQuery();
        $this->tgBot->sendRequest('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->getCache('edit_msg_id'),
            'text' => "Change status for SupplyOrder #{$supplyOrder->number}",
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => 'Confirm', 'callback_data' => "confirmSupplyOrder:$supplyOrder->id"]],
                [['text' => 'Close', 'callback_data' => "closeSupplyOrder:$supplyOrder->id"]],
            ]),
        ]);
    }

    public function getSupplyOrderButtons(SupplyOrder $supplyOrder): array
    {
        $buttons = [];
        if (!$supplyOrder->isConfirmed()) {
            $buttons[] = [['text' => '✅ Confirm', 'callback_data' => "confirmSupplyOrder:$supplyOrder->id"]];
        }
        if ($supplyOrder->isConfirmed() && !$supplyOrder->isClosed()) {
            $buttons[] = [['text' => 'Change status', 'callback_data' => "changeSupplyOrderStatus:$supplyOrder->id"]];
        }
        if ($supplyOrder->isReadyForClose()) {
            $buttons[] = [['text' => 'Close order', 'callback_data' => "closeSupplyOrder:$supplyOrder->id"]];
        }
        return $buttons;
    }

    public function handleInlineQuery($inlineQuery): void
    {
        $search = $inlineQuery['query'] ?? '';
        dump("Search: $search");

        $results = SupplyOrder::query()
            ->search($search)
            ->limit(30)
            ->get()
            ->map(function (SupplyOrder $order) {
                return [
                    'type' => 'article',
                    'id' => 'order_' . $order->id,
                    'title' => "{$order->productCategory->name} - {$order->getStatus()}",
                    'description' => "Category: {$order->productCategory->name}, Date: {$order->created_at->format('d M Y H:i')}",
                    'input_message_content' => [
                        'message_text' => "/select_supply_order $order->id"
                    ]
                ];
            });

        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $inlineQuery['id'],
            'results' => $results->toArray(),
            'cache_time' => 0,
        ]);
    }

    public function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
//            [['text' => '🔍 Search order', 'callback_data' => 'searchSupplyOrder']],
            [['text' => '➕ Create SupplyOrder', 'callback_data' => 'createSupplyOrder']],
            [['text' => '🔍 SupplyOrders', 'switch_inline_query_current_chat' => '']],
        ]);
    }
}
