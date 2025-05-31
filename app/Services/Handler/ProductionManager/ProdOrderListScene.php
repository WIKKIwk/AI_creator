<?php

namespace App\Services\Handler\ProductionManager;

use App\Listeners\ProdOrderNotification;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;

class ProdOrderListScene implements SceneHandlerInterface
{
    protected TgBot $tgBot;
    protected Cache $cache;

    protected ProdOrderService $prodOrderService;

    public const states = [
        'prodOrder_prev' => 'prodOrder_prev',
        'prodOrder_next' => 'prodOrder_next',
    ];

    public function __construct(protected ProductionManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleScene(): void
    {
        $this->prodOrderList();
    }

    public function prodOrderPrev($page): void
    {
        if ($page <= 0) {
            $this->tgBot->answerCbQuery(['text' => 'You are on the first page']);
            return;
        }

        $this->prodOrderList($page);
    }

    public function prodOrderNext($page): void
    {
        $totalOrders = ProdOrderGroup::query()->count();
        if ($page > $totalOrders) {
            $this->tgBot->answerCbQuery(['text' => 'You are on the last page']);
            return;
        }

        $this->prodOrderList($page);
    }

    public function confirmListOrder($groupId): void
    {
        /** @var ProdOrderGroup $prodOrderGroup */
        $prodOrderGroup = ProdOrderGroup::find($groupId);
        if (!$prodOrderGroup) {
            $this->tgBot->answerCbQuery(['text' => 'ProdOrder not found']);
            return;
        }

        if ($prodOrderGroup->isConfirmed()) {
            $this->tgBot->answerCbQuery(['text' => 'ProdOrder is already confirmed']);
            return;
        }

        $prodOrderGroup->confirm();
        $this->tgBot->answerCbQuery(['text' => '✅ ProdOrder confirmed successfully']);

        $this->prodOrderList(id: $prodOrderGroup->id); // Reset to first page after confirmation
    }

    public function prodOrderList($page = 1, $id = null): void
    {
        /** @var ProdOrderGroup $prodOrderGroup */
        $prodOrderGroup = ProdOrderGroup::query()
            ->when($id, fn($q) => $q->where('id', $id))
            ->offset(($page - 1))
            ->orderByDesc('created_at')
            ->first();

        if (!$prodOrderGroup) {
            $this->tgBot->answerCbQuery(['text' => 'No production orders found']);
            return;
        }

        $pages = ProdOrderGroup::query()->count();
        $message = "<b>Production Orders (Page $page of $pages):</b>\n\n";
        $message .= ProdOrderNotification::getProdOrderMsg($prodOrderGroup);

        $buttons = [];
        if (!$prodOrderGroup->isConfirmed()) {
            $buttons[] = [['text' => '✅ Confirm order', 'callback_data' => "confirmListOrder:$prodOrderGroup->id"]];
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
}
