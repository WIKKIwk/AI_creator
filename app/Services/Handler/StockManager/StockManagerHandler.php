<?php

namespace App\Services\Handler\StockManager;

use App\Enums\ProdOrderStepStatus;
use App\Enums\SupplyOrderStatus;
use App\Listeners\ProdOrderNotification;
use App\Listeners\StepExecutionNotification;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\User;
use App\Services\Handler\BaseHandler;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgMessageService;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

class StockManagerHandler extends BaseHandler
{
    protected array $sceneHandlers = [
        'selectMaterial' => SelectMaterialScene::class,
        'selectExecution' => SelectExecutionScene::class,
        'compareSupplyOrder' => CompareSupplyOrderScene::class,
    ];

    protected array $callbackHandlers = [
        'cancelMaterial' => [SelectMaterialScene::class, 'cancelMaterial'],
        'cancelExecution' => [SelectExecutionScene::class, 'cancelExecution'],
        'cancelCompare' => [CompareSupplyOrderScene::class, 'cancelCompare'],
    ];

    protected const templates = [
        'addRawMaterial' => <<<HTML
<b>Form inputs</b>

<b>1) Name</b>: {name}
<b>2) Quantity</b>: {quantity}
<b>3) Price</b>: {price}
HTML,

    ];

    /**
     * @throws GuzzleException
     */
    public function validateUser(User $user): bool
    {
        if (!$user->warehouse_id) {
            $this->tgBot->sendMsg([
                'chat_id' => $user->chat_id,
                'text' => "You are not assigned to any warehouse. Please contact the manager.",
            ]);
            return false;
        }
        return true;
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

    public function handleText(string $text): void
    {
        $activeState = $this->getState();

        if (str_starts_with($text, '/select_prod_order')) {
            $orderId = trim(str_replace('/select_prod_order ', '', $text));
            $this->selectProdOrder($orderId);
            return;
        }

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

    public function selectProdOrder($orderId): void
    {
        $this->tgBot->answerCbQuery();
        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::query()->started()->find($orderId);

        if (!$prodOrder) {
            $this->tgBot->sendRequestAsync('sendMessage', [
                'chat_id' => $this->tgBot->chatId,
                'text' => "❌ Order not found!",
            ]);
            return;
        }

        $message = "<b>Selected order details:</b>\n\n";
        $message .= TgMessageService::getProdOrderMsg($prodOrder);

        $stepButtons = [];
        foreach ($prodOrder->steps as $step) {
            $stepButtons[] = [['text' => $step->workStation->name, 'callback_data' => "selectPoStep:$step->id"]];
        }

        $messageId = $this->getCache('edit_msg_id');
        if ($messageId) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $messageId,
                'text' => $message,
                'reply_markup' => TelegramService::getInlineKeyboard($stepButtons),
                'parse_mode' => 'HTML',
            ]);
        } else {
            $this->tgBot->sendRequestAsync('sendMessage', [
                'chat_id' => $this->tgBot->chatId,
                'text' => $message,
                'reply_markup' => TelegramService::getInlineKeyboard($stepButtons),
                'parse_mode' => 'HTML',
            ]);
        }
    }

    public function selectPoStep($stepId): void
    {
        $step = $this->getStep($stepId);
        $this->tgBot->answerCbQuery();

        $message = "<b>Selected step details:</b>\n\n";
        $message .= TgMessageService::getPoStepMsg($step);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => 'Materials', 'callback_data' => "materialsList:$step->id"],
                    ['text' => 'Executions', 'callback_data' => "executionsList:$step->id"]
                ],
                [['text' => '⬅️ Back', 'callback_data' => "selectProdOrder:$step->prod_order_id"]],
            ]),
        ]);

        $this->setCache('edit_msg_id', $this->tgBot->getMessageId());
    }

    public function materialsList($stepId): void
    {
        $step = $this->getStep($stepId);

        $buttons = [];
        $message = "<b>Materials of {$step->workStation->name} step:</b>\n";
        foreach ($step->materials as $index => $material) {
            $index++;

            $measureUnit = $material->product->getMeasureUnit();
            $message .= "\n";
            $message .= "$index) Material: <b>{$material->product->catName}</b>\n";
            $message .= "Required: <b>$material->required_quantity {$measureUnit->getLabel()}</b>\n";
            $message .= "Available: <b>$material->available_quantity {$measureUnit->getLabel()}</b>\n";
            $message .= "Used: <b>$material->used_quantity {$measureUnit->getLabel()}</b>\n";

            $buttons[] = [['text' => $material->product->catName, 'callback_data' => "selectMaterial:$material->id"]];
        }

        if ($step->status != ProdOrderStepStatus::Completed) {
            $buttons = array_merge($buttons, [
                [['text' => '⬅️ Back', 'callback_data' => "selectPoStep:$stepId"]],
            ]);
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard($buttons),
        ]);
    }

    public function executionsList($stepId): void
    {
        $step = $this->getStep($stepId);

        $buttons = [];
        $message = "<b>Executions of {$step->workStation->name} step:</b>\n";
        foreach ($step->executions as $index => $execution) {
            $buttons[] = [
                [
                    'text' => "{$execution->executedBy->name} at {$execution->created_at->format('d M Y H:i')}",
                    'callback_data' => "selectExecution:$execution->id"
                ]
            ];
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard(
                array_merge($buttons, [
                    [['text' => '⬅️ Back', 'callback_data' => "selectPoStep:$stepId"]],
                ])
            ),
        ]);
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
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => 'Compare products', 'callback_data' => "compareSupplyOrder:$supplyOrder->id"]]
            ]),
        ]);
    }

    public function handleInlineQuery($inlineQuery): void
    {
        $search = $inlineQuery['query'] ?? '';
        dump("Search: $search");

        if (str_starts_with($search, 'compareSo')) {
            $search = str_replace('compareSo', '', $search);

            $results = SupplyOrder::query()
                ->where('status', SupplyOrderStatus::AwaitingWarehouseApproval)
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
        } else {
            $results = ProdOrder::query()
                ->ownWarehouse()
                ->started()
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
        }

        dump($results->toArray());

        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $inlineQuery['id'],
            'results' => $results->toArray(),
            'cache_time' => 0,
        ]);
    }

    protected function getStep(int $stepId): ?ProdOrderStep
    {
        /** @var ?ProdOrderStep $step */
        $step = ProdOrderStep::query()->find($stepId);
        return $step;
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [['text' => '🔍 Search PO', 'switch_inline_query_current_chat' => '']],
            [['text' => '🔍 Compare SO', 'switch_inline_query_current_chat' => 'compareSo']],
        ]);
    }
}
