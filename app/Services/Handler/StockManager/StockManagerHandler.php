<?php

namespace App\Services\Handler\StockManager;

use App\Listeners\StepExecutionNotification;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\User;
use App\Services\Handler\BaseHandler;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

class StockManagerHandler extends BaseHandler
{
    protected array $promises = [];

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
            $message .= StepExecutionNotification::getExecutionMsg($poExecution);

            $this->tgBot->answerCbQuery(['text' => '✅ Execution approved!'], true);
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
        } catch (Throwable $e) {
            $message = "<i>❌ {$e->getMessage()}!</i>\n\n";
            $message .= StepExecutionNotification::getExecutionMsg($poExecution);

            $this->tgBot->answerCbQuery(['text' => '❌ Error occurred!'], true);
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
        }
    }

    public function handleInlineQuery($inlineQuery): void
    {
        $search = $inlineQuery['query'] ?? '';
        $search = mb_strtolower(trim($search));
        dump("Search: $search");

        $results = ProdOrder::query()
            ->whereRaw('LOWER(number) LIKE ?', ["%$search%"])
            ->limit(30)
            ->get()
            ->map(function (ProdOrder $order) {
                return [
                    'type' => 'article',
                    'id' => 'order_' . $order->id,
                    'title' => $order->number,
                    'description' => "{$order->product->catName}: $order->quantity {$order->product->getMeasureUnit()->getLabel()}",
                    'input_message_content' => [
                        'message_text' => "/select_order $order->id"
                    ],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '📦 Materials', 'callback_data' => "selectMaterial:$order->id"],
                                ['text' => '🧪 Executions', 'callback_data' => "selectExecution:$order->id"],
                            ]
                        ]
                    ],
                ];
            });

        dump($results->toArray());

        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $inlineQuery['id'],
            'results' => $results->toArray(),
            'cache_time' => 0,
        ]);
    }

    protected function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [['text' => '🔍 Search PO', 'switch_inline_query_current_chat' => '']],
        ]);
    }
}
