<?php

namespace App\Services\Handler\ProductionManager;

use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;
use Throwable;

class DeclineExecutionScene implements SceneHandlerInterface
{
    protected TgBot $tgBot;
    protected Cache $cache;

    protected ProdOrderService $prodOrderService;

    public const states = [
        'declineExecution_comment' => 'declineExecution_comment',
    ];

    public function __construct(protected ProductionManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleText($text): void
    {
        $state = $this->handler->getState();
        dump("Action: $state, Text: $text");
        if ($state == self::states['declineExecution_comment']) {
            $this->inputComment($text);
        }

        $this->tgBot->rmLastMsg();
    }

    public function handleScene(array $params = []): void
    {
        $executionId = $params[0];
        dump($executionId);
        /** @var ProdOrderStepExecution $execution */
        $execution = ProdOrderStepExecution::query()->find($executionId);

        $form = ['execution_id' => $execution->id];
        $this->handler->setCacheArray('executionForm', $form);
        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
        $this->handler->setState(self::states['declineExecution_comment']);

        $message = "<b>Execution details</b>\n\n";
        $message .= TgMessageService::getExecutionMsg($execution);
        $message .= "\n<b>Input decline comment:</b>\n";

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '⬅️ Back', 'callback_data' => 'cancelDecline']],
            ]),
        ]);
    }

    public function inputComment($text): void
    {
        $executionId = $this->handler->getCacheArray('executionForm')['execution_id'];
        $execution = $this->getExecution($executionId);

        try {
            $this->prodOrderService->declineExecutionProdManager($execution, $text);

            $message = TgMessageService::getExecutionMsg($execution);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '⬅️ Back', 'callback_data' => 'cancelDecline']],
                ]),
            ]);

            $this->handler->resetCache();
            $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
        } catch (Throwable $e) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => "<b>Error:</b> " . $e->getMessage(),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '⬅️ Back', 'callback_data' => 'cancelDecline']],
                ]),
            ]);
        }
    }

    public function cancelDecline(): void
    {
        $this->handler->resetCache();
        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
        $this->handler->sendMainMenu(true);
    }

    public function getExecution($executionId): ProdOrderStepExecution
    {
        /** @var ProdOrderStepExecution $execution */
        $execution = ProdOrderStepExecution::query()->findOrFail($executionId);
        return $execution;
    }
}
