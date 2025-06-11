<?php

namespace App\Services\Handler\ProductionManager;

use App\Enums\RoleType;
use App\Models\Inventory\Inventory;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\ProdTemplate\ProdTemplate;
use App\Services\Handler\BaseHandler;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgMessageService;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class ProductionManagerHandler extends BaseHandler
{
    protected array $sceneHandlers = [
        'createProdTemplate' => CreateProdTemplateScene::class,
        'createProdTemplateStep' => CreateProdTemplateStepScene::class,
        'createProdOrder' => CreateProdOrderScene::class,
        'startProdOrder' => StartProdOrderScene::class,
        'workStationsList' => WorkStationManagerScene::class,
    ];

    protected array $callbackHandlers = [
        'confirmProdOrder' => [CreateProdOrderScene::class, 'confirmProdOrder'],
        'cancelStartOrder' => [StartProdOrderScene::class, 'cancelStartOrder'],
        'cancelProdOrder' => [CreateProdOrderScene::class, 'cancelProdOrder'],
        'cancelProdTemplate' => [CreateProdTemplateScene::class, 'cancelProdTemplate'],
        'cancelStep' => [CreateProdTemplateStepScene::class, 'cancelStep'],

        'workStationsList' => [WorkStationManagerScene::class, 'handleScene'],
        'showWorkStation' => [WorkStationManagerScene::class, 'showWorkStation'],
        'assignProdOrders' => [WorkStationManagerScene::class, 'assignProdOrders'],
        'assignProdOrder' => [WorkStationManagerScene::class, 'assignProdOrder'],
        'backWsMenu' => [WorkStationManagerScene::class, 'backWsMenu'],
        'backWsShowMenu' => [WorkStationManagerScene::class, 'backWsShowMenu'],

        'confirmListOrder' => [ProdOrderListCb::class, 'confirmOrder'],
        'prodOrdersList' => [ProdOrderListCb::class, 'sendList'],
        'prodOrderPrev' => [ProdOrderListCb::class, 'prev'],
        'prodOrderNext' => [ProdOrderListCb::class, 'next'],
    ];

    public const templates = [
        'tmp' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
        'prodOrderGroup' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
        'prodTemplate' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
        'prodTemplateStep' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
    ];

    public function handleText(string $text): void
    {
        $activeState = $this->getState();

        if (str_starts_with($text, '/select_prod_order')) {
            $orderId = trim(str_replace('/select_prod_order ', '', $text));
            $this->selectProdOrder($orderId);
            return;
        }

        if (str_starts_with($text, '/select_prod_template')) {
            $templateId = trim(str_replace('/select_prod_template ', '', $text));
            $this->selectProdTemplate($templateId);
            return;
        }

        if ($activeState || $this->getScene()) {
            $this->tgBot->rmLastMsg();
            return;
        }

        $this->sendMainMenu();
    }

    public function approveExecution($executionId): void
    {
        /** @var ProdOrderStepExecution $poExecution */
        $poExecution = ProdOrderStepExecution::query()->find($executionId);

        try {
            /** @var ProdOrderService $poService */
            $poService = app(ProdOrderService::class);
            $poService->approveExecution($poExecution);

            $message = "<b>✅ Execution approved!</b>\n\n";
            $message .= TgMessageService::getExecutionMsg($poExecution);

            $this->tgBot->answerCbQuery(['text' => '✅ Execution approved!'], true);
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
        } catch (Throwable $e) {
            $message = "<i>❌ {$e->getMessage()}!</i>\n\n";
            $message .= TgMessageService::getExecutionMsg($poExecution);

            $this->tgBot->answerCbQuery(['text' => '❌ Error occurred!'], true);
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
        }
    }

    public function selectProdTemplate($templateId): void
    {
        dump("Selecting ProdTemplate: $templateId");
        $this->tgBot->answerCbQuery();
        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()->find($templateId);

        if (!$prodTemplate) {
            $this->tgBot->sendRequestAsync('sendMessage', [
                'chat_id' => $this->tgBot->chatId,
                'text' => "❌ Template not found!",
            ]);
            return;
        }

        $message = "<b>ProdTemplate details:</b>\n\n";
        $message .= TgMessageService::getProdTemplateMsg($prodTemplate);

        $messageId = $this->getCache('edit_msg_id');
        dump("msg_id: $messageId");

        $this->tgBot->sendRequestAsync($messageId ? 'editMessageText' : 'sendMessage', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '➕ Create step', 'callback_data' => "createProdTemplateStep:$templateId"]]
            ]),
        ]);
    }

    public function selectProdOrder($orderId): void
    {
        dump("Selecting ProdOrder: $orderId");
        $this->tgBot->answerCbQuery();
        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::query()->find($orderId);

        if (!$prodOrder) {
            $this->tgBot->sendRequestAsync('sendMessage', [
                'chat_id' => $this->tgBot->chatId,
                'text' => "❌ Order not found!",
            ]);
            return;
        }

        $message = "<b>ProdOrder details:</b>\n\n";
        $message .= TgMessageService::getProdOrderMsg($prodOrder);

        $messageId = $this->getCache('edit_msg_id');
        dump("msg_id: $messageId");

        $this->tgBot->sendRequestAsync($messageId ? 'editMessageText' : 'sendMessage', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard($this->getProdOrderButtons($prodOrder)),
        ]);
    }

    public function inventoryList(): void
    {
        $inventoryMsg = "<b>Inventory List</b>\n\n";

        /** @var Collection<Inventory> $inventories */
        $inventories = Inventory::query()->get();
        foreach ($inventories as $inventory) {
            if ($inventory->quantity > 0) {
                $product = $inventory->product;
                $inventoryMsg .= "<b>{$product->catName}:</b> $inventory->quantity {$product->getMeasureUnit()->getLabel()}\n";
            }
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $inventoryMsg,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🔙 Back', 'callback_data' => 'backMainMenu']]
            ]),
        ]);
    }

    public function handleInlineQuery($inlineQuery): void
    {
        $search = $inlineQuery['query'] ?? '';
        dump("Search: $search");

        if (str_starts_with($search, 'prodTmp')) {
            $search = str_replace('prodTmp', '', $search);
            dump($search);
            $prodTemplates = ProdTemplate::query()->get();

            $this->tgBot->sendRequest('answerInlineQuery', [
                'inline_query_id' => $inlineQuery['id'],
                'results' => TelegramService::inlineResults($prodTemplates, 'id', 'name', '/select_prod_template '),
                'cache_time' => 0,
            ]);
            return;
        }

        $results = ProdOrder::query()
            ->ownWarehouse()
            ->search($search)
            ->limit(30)
            ->get()
            ->map(function (ProdOrder $order) {
                return [
                    'type' => 'article',
                    'id' => 'order_' . $order->id,
                    'title' => $order->number,
                    'description' => "{$order->product->catName}: $order->quantity {$order->product->getMeasureUnit()->getLabel()}",
                    'input_message_content' => [
                        'message_text' => "/select_prod_order $order->id"
                    ]
                ];
            });

        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $inlineQuery['id'],
            'results' => $results->toArray(),
            'cache_time' => 0,
        ]);
    }

    public function getProdOrderButtons(ProdOrder $prodOrder): array
    {
        $buttons = [];
        if (!$prodOrder->isConfirmed()) {
            $buttons[] = [['text' => '✅ Confirm', 'callback_data' => "confirmProdOrder:$prodOrder->id"]];
        }
        if (!$prodOrder->isStarted()) {
            $buttons[] = [['text' => '📦 Start', 'callback_data' => "startProdOrder:$prodOrder->id"]];
        }

        return $buttons;
    }

    public function getMainKb(): array
    {
        $wsButton = [];
        if ($this->user->role != RoleType::SENIOR_PRODUCTION_MANAGER) {
            $wsButton[] = [['text' => '🛠 WorkStations', 'callback_data' => 'workStationsList']];
        }

        return TelegramService::getInlineKeyboard([
            [
                ['text' => '🔍 ProdTemplates', 'switch_inline_query_current_chat' => 'prodTmp'],
                ['text' => '➕ Create ProdTemplate', 'callback_data' => 'createProdTemplate'],
            ],
            [
                ['text' => '🔍 ProdOrder', 'switch_inline_query_current_chat' => ''],
                ['text' => '➕ Create ProdOrder', 'callback_data' => 'createProdOrder']
            ],
            ...$wsButton,
            [['text' => '📋 Inventory', 'callback_data' => 'inventoryList']],
        ]);
    }
}
