<?php

namespace App\Services\Handler\ProductionManager;

use App\Listeners\ProdOrderNotification;
use App\Models\ProdOrder\ProdOrder;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;
use Illuminate\Support\Arr;

class StartProdOrderScene implements SceneHandlerInterface
{
    protected TgBot $tgBot;
    protected Cache $cache;

    protected ProdOrderService $prodOrderService;

    public const states = [];

    public function __construct(protected ProductionManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleScene(array $params = []): void
    {
        $orderId = $params[0];
        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::query()->find($orderId);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());

        try {
            $insufficientAssets = $this->prodOrderService->checkStart($prodOrder);
            if (!empty($insufficientAssets)) {
                $this->tgBot->answerCbQuery(['text' => '⚠️ Insufficient assets!']);
                $message = "<i>⚠️ Insufficient assets:</i>\n\n";

                foreach ($insufficientAssets as $missingAssets) {
                    foreach ($missingAssets as $item) {
                        $product = Arr::get($item, 'product');
                        $qty = Arr::get($item, 'quantity');
                        $category = Arr::get($item, 'category');
                        $measureUnit = Arr::get($item, 'measure_unit');

                        $message .= "<b>$category {$product['name']}: $qty $measureUnit</b>\n";
                    }
                }

                $message .= "\n<b>Would you like to create Supply Orders for these assets?</b>";

                $this->tgBot->sendRequestAsync('editMessageText', [
                    'chat_id' => $this->tgBot->chatId,
                    'message_id' => $this->tgBot->getMessageId(),
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'reply_markup' => TelegramService::getInlineKeyboard([
                        [
                            ['text' => '⬅️ Back', 'callback_data' => "cancelStartOrder:$prodOrder->id"],
                            ['text' => '✅ Confirm', 'callback_data' => "confirmSupplyOrderPO:$prodOrder->id"]
                        ],
                    ]),
                ]);
                return;
            }


            $this->prodOrderService->start($prodOrder);
            $this->tgBot->answerCbQuery(['text' => '✅ ProdOrder started successfully!']);

            $message = "<b>✅ ProdOrder started successfully!</b>\n\n";
            $message .= TgMessageService::getProdOrderMsg($prodOrder);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard($this->handler->getProdOrderButtons($prodOrder)),
            ]);
        } catch (\Throwable $e) {
            $this->tgBot->answerCbQuery(['text' => '❌ Error starting ProdOrder!']);

            $message = "<i>❌ Error starting ProdOrder: {$e->getMessage()}</i>\n\n";
            $message .= TgMessageService::getProdOrderMsg($prodOrder);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard($this->handler->getProdOrderButtons($prodOrder)),
            ]);
        }
    }

    public function confirmSupplyOrderPO($prodOrderId): void
    {
        $prodOrder = $this->getProdOrder($prodOrderId);

        $this->prodOrderService->start($prodOrder);

        $this->tgBot->answerCbQuery(['text' => '✅ Supply Orders created successfully!']);

        $message = "<b>✅ Supply Orders created successfully!</b>\n\n";
        $message .= TgMessageService::getProdOrderMsg($prodOrder);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard($this->handler->getProdOrderButtons($prodOrder)),
        ]);

        $this->handler->resetCache();
    }

    public function cancelStartOrder($prodOrderId): void
    {
        $this->handler->resetCache();
        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
        $this->handler->selectProdOrder($prodOrderId);
    }

    public function getProdOrder(int $prodOrderId): ProdOrder
    {
        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::query()->find($prodOrderId);
        return $prodOrder;
    }
}
