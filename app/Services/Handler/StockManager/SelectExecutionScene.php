<?php

namespace App\Services\Handler\StockManager;

use App\Enums\ProdOrderStepStatus;
use App\Listeners\ProdOrderNotification;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;

class SelectExecutionScene implements SceneHandlerInterface
{
    public TgBot $tgBot;
    public Cache $cache;
    public ProdOrderService $prodOrderService;

    public function __construct(public StockManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleScene($params = []): void
    {
        dump("Scene: " . $this->handler->getScene());
        $this->tgBot->answerCbQuery();
        $executionId = $params[0];
        $execution = $this->getExecution($executionId);

        $buttons = [];
        dump($execution->prodOrderStep->status, ProdOrderStepStatus::Completed);
        if ($execution->prodOrderStep->status != ProdOrderStepStatus::Completed) {
            $buttons[] = [['text' => 'Approve', 'callback_data' => "approveExecution:$execution->id"]];
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => ProdOrderNotification::getExecutionMsg($execution),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                ...$buttons,
                [['text' => '⬅️ Back', 'callback_data' => "cancelExecution:$execution->prod_order_step_id"]],
            ]),
        ]);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
    }

    public function cancelExecution($stepId): void
    {
        $this->handler->resetCache();
        $this->handler->executionsList($stepId);
    }

    public function getExecution($executionId): ?ProdOrderStepExecution
    {
        /** @var ?ProdOrderStepExecution $execution */
        $execution = ProdOrderStepExecution::query()->find($executionId);
        return $execution;
    }
}
