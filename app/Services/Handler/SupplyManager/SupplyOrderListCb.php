<?php

namespace App\Services\Handler\SupplyManager;

use App\Listeners\SupplyOrderNotification;
use App\Models\SupplyOrder\SupplyOrder;
use App\Services\Cache\Cache;
use App\Services\SupplyOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;

class SupplyOrderListCb
{
    protected TgBot $tgBot;
    protected Cache $cache;

    protected SupplyOrderService $supplyOrderService;

    public function __construct(protected SupplyManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->supplyOrderService = app(SupplyOrderService::class);
    }

    public function sendList($page = 1, $id = null): void
    {
        /** @var SupplyOrder $supplyOrder */
        $supplyOrder = SupplyOrder::query()
            ->when($id, fn($q) => $q->where('id', $id))
            ->offset(($page - 1))
            ->orderByDesc('created_at')
            ->first();

        if (!$supplyOrder) {
            $this->tgBot->answerCbQuery(['text' => 'No orders found']);
            return;
        }

        $pages = SupplyOrder::query()->count();
        $message = "<b>Supply orders (Page $page of $pages):</b>\n\n";
        $message .= SupplyOrderNotification::getSupplyOrderMsg($supplyOrder);

        $buttons = [];
        if (!$supplyOrder->isConfirmed()) {
            $buttons[] = [['text' => '✅ Confirm order', 'callback_data' => "confirmListOrder:$supplyOrder->id"]];
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                ...$buttons,
                [
                    ['text' => 'Previous', 'callback_data' => 'supplyOrderPrev:' . $page - 1],
                    ['text' => 'Next', 'callback_data' => 'supplyOrderNext:' . $page + 1],
                ],
                [['text' => 'Main Menu', 'callback_data' => 'backMainMenu']]
            ]),
        ]);
    }

    public function prev($page): void
    {
        if ($page <= 0) {
            $this->tgBot->answerCbQuery(['text' => 'You are on the first page']);
            return;
        }

        $this->sendList($page);
    }

    public function next($page): void
    {
        $totalOrders = SupplyOrder::query()->count();
        if ($page > $totalOrders) {
            $this->tgBot->answerCbQuery(['text' => 'You are on the last page']);
            return;
        }

        $this->sendList($page);
    }

    public function confirmOrder($groupId): void
    {
        $supplyOrder = SupplyOrder::find($groupId);
        if (!$supplyOrder) {
            $this->tgBot->answerCbQuery(['text' => 'SupplyOrder not found']);
            return;
        }

        if ($supplyOrder->isConfirmed()) {
            $this->tgBot->answerCbQuery(['text' => 'SupplyOrder is already confirmed']);
            return;
        }

        $supplyOrder->confirm();
        $this->tgBot->answerCbQuery(['text' => '✅ SupplyOrder confirmed successfully']);

        $this->sendList(id: $supplyOrder->id); // Reset to first page after confirmation
    }
}
