<?php

namespace App\Services\Handler\ProductionManager;

use App\Enums\ProdOrderStepStatus;
use App\Models\ProdOrder\ProdOrder;
use App\Models\WorkStation;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;
use Ramsey\Collection\Collection;

class WorkStationManagerScene implements SceneHandlerInterface
{
    public TgBot $tgBot;
    public Cache $cache;

    public ProdOrderService $prodOrderService;

    public function __construct(public ProductionManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleScene(array $params = []): void
    {
        $buttons = $this->handler->user->workStations->map(fn($s) => [
            ['text' => $s->name, 'callback_data' => 'showWorkStation:' . $s->id]
        ])->toArray();

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => __('telegram.workstations_list'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($buttons, [
                    [['text' => __('telegram.back'), 'callback_data' => 'backMainMenu']]
                ])
            ),
        ]);
    }

    public function showWorkStation(int $id, $title = null): void
    {
        /** @var WorkStation $ws */
        $ws = WorkStation::with(['category', 'organization', 'prodManager', 'prodOrder'])->findOrFail($id);
        $this->handler->setCache('current_work_station', $ws->id);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $title . $this->getWsMsg(),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => __('telegram.assign_prodorder'), 'callback_data' => 'assignProdOrders:' . $ws->id]],
                [['text' => __('telegram.back'), 'callback_data' => 'backWsMenu']],
            ]),
        ]);
    }

    public function assignProdOrders($wsId): void
    {
        $ws = $this->getCurrenWs($wsId);

        /** @var Collection<ProdOrder> $orders */
        $orders = ProdOrder::query()
            ->whereHas('steps', fn($query) => $query
                ->whereNot('status', ProdOrderStepStatus::Completed)
                ->where('work_station_id', $ws->id)
            )
            ->get();

        $buttons = [
            [['text' => __('telegram.no_po'), 'callback_data' => 'assignProdOrder:0']]
        ];
        foreach ($orders as $order) {
            $buttons[] = [['text' => $order->number, 'callback_data' => 'assignProdOrder:' . $order->id]];
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getWsMsg(__('telegram.select_prodorder')),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($buttons, [
                    [['text' => __('telegram.back'), 'callback_data' => "backWsShowMenu:$wsId"]]
                ])
            ),
        ]);
    }

    public function assignProdOrder($orderId): void
    {
        $ws = $this->getCurrenWs();

        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::query()->findOrFail($orderId);
        $this->prodOrderService->assignProdOrderToWorkStation($ws, $prodOrder);

        $this->showWorkStation($ws->id, '<b>' . __('telegram.assigned_success') . '</b>');
    }

    public function backWsMenu(): void
    {
        $this->handleScene();
    }

    public function backWsShowMenu($wsId): void
    {
        $this->showWorkStation($wsId);
    }

    protected function getWsMsg(string $prompt = null, $error = null): string
    {
        return strtr($this->handler::templates['tmp'], [
            '{errorMsg}' => $error ?? '',
            '{details}' => TgMessageService::getWorkStationMsg($this->getCurrenWs()),
            '{prompt}' => $prompt,
        ]);
    }

    protected function getCurrenWs($wsId = null): WorkStation
    {
        $wsId = $wsId ?: $this->handler->getCache('current_work_station');
        /** @var WorkStation $ws */
        $ws = WorkStation::query()->findOrFail($wsId);
        return $ws;
    }
}
