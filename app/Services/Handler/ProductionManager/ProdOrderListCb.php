<?php

namespace App\Services\Handler\ProductionManager;

use App\Listeners\ProdOrderNotification;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Services\Cache\Cache;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;

class ProdOrderListCb
{
    protected TgBot $tgBot;
    protected Cache $cache;

    protected ProdOrderService $prodOrderService;

    public function __construct(protected ProductionManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function confirmOrder($groupId): void
    {
        /** @var ProdOrderGroup $poGroup */
        $poGroup = ProdOrderGroup::find($groupId);
        if (!$poGroup) {
            $this->tgBot->answerCbQuery(['text' => 'ProdOrder not found']);
            return;
        }

        if ($poGroup->isConfirmed()) {
            $this->tgBot->answerCbQuery(['text' => 'ProdOrder is already confirmed']);
            return;
        }

        $poGroup->confirm();
        $this->tgBot->answerCbQuery(['text' => '✅ ProdOrder confirmed successfully']);

        $this->sendList(id: $poGroup->id); // Reset to first page after confirmation
    }

    public function sendList($page = 1, $id = null): void
    {
        /** @var ProdOrderGroup $poGroup */
        $poGroup = ProdOrderGroup::query()
            ->when($id, fn($q) => $q->where('id', $id))
            ->offset(($page - 1))
            ->orderByDesc('created_at')
            ->first();

        if (!$poGroup) {
            $this->tgBot->answerCbQuery(['text' => 'No orders found']);
            return;
        }

        $pages = ProdOrderGroup::query()->count();
        $message = "<b>Production Orders (Page $page of $pages):</b>\n\n";
        $message .= ProdOrderNotification::getProdOrderMsg($poGroup);

        $buttons = [];
        if (!$poGroup->isConfirmed()) {
            $buttons[] = [['text' => '✅ Confirm order', 'callback_data' => "confirmListOrder:$poGroup->id"]];
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                ...$buttons,
                [
                    ['text' => 'Previous', 'callback_data' => 'prodOrderPrev:' . $page - 1],
                    ['text' => 'Next', 'callback_data' => 'prodOrderNext:' . $page + 1],
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
        $totalOrders = ProdOrderGroup::query()->count();
        if ($page > $totalOrders) {
            $this->tgBot->answerCbQuery(['text' => 'You are on the last page']);
            return;
        }

        $this->sendList($page);
    }
}
