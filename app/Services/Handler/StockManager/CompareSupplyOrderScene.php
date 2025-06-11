<?php

namespace App\Services\Handler\StockManager;

use App\Listeners\SupplyOrderNotification;
use App\Models\SupplyOrder\SupplyOrder;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\SupplyOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;

class CompareSupplyOrderScene implements SceneHandlerInterface
{
    public TgBot $tgBot;
    public Cache $cache;
    public SupplyOrderService $supplyOrderService;

    protected const states = [
        'inputQuantity' => 'inputQuantity'
    ];

    public function __construct(public StockManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->supplyOrderService = app(SupplyOrderService::class);
    }

    public function handleText($text): void
    {
        $activeState = $this->handler->getState();

        switch ($activeState) {
            case self::states['inputQuantity']:
                $this->inputQuantity($text);
                return;
        }

        if ($activeState) {
            $this->tgBot->rmLastMsg();
            return;
        }

        $this->handler->sendMainMenu();
    }

    public function handleScene(array $params = []): void
    {
        $this->tgBot->answerCbQuery();
        $supplyOrderId = $params[0];

        /** @var SupplyOrder $supplyOrder */
        $supplyOrder = SupplyOrder::query()->find($supplyOrderId);

        $this->handler->setCacheArray('compareSupplyForm', [
            'id' => $supplyOrder->id,
            'currentProduct' => null,
            'products' => []
        ]);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getCompareMsg($supplyOrder),
            'parse_mode' => 'HTML',
            'reply_markup' => $this->getInlineButtons($supplyOrder),
        ]);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
    }

    public function selectProduct($productId): void
    {
        $form = $this->handler->getCacheArray('compareSupplyForm');
        $form['currentProduct'] = $productId;
        $this->handler->setCacheArray('compareSupplyForm', $form);

        $this->handler->setState(self::states['inputQuantity']);

        dump($form, $this->handler->getState());

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getCompareMsg(prompt: 'Input quantity for product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => $this->getInlineButtons(),
        ]);
    }

    public function inputQuantity($quantity): void
    {
        $form = $this->handler->getCacheArray('compareSupplyForm');
        $currentProductId = $form['currentProduct'] ?? null;

        if (!is_numeric($quantity) || (int)$quantity <= 0) {
            $message = "<i>❌ Invalid quantity input</i>\n\n";
            $message .= $this->getCompareMsg(prompt: 'Input quantity for product:');

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => $this->getInlineButtons(),
            ]);
            return;
        }

        $form['products'][$currentProductId] = (int)$quantity;
        $this->handler->setCacheArray('compareSupplyForm', $form);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getCompareMsg(prompt: 'Select product for compare:'),
            'parse_mode' => 'HTML',
            'reply_markup' => $this->getInlineButtons(withSaveBtn: true),
        ]);
    }

    public function saveCompare($supplyOrderId): void
    {
        $form = $this->handler->getCacheArray('compareSupplyForm');
        $formProducts = $form['products'] ?? [];

        $this->handler->resetCache();
        $this->handler->setCacheArray('compareSupplyForm', []);

        $supplyOrder = $this->getSupplyOrder($supplyOrderId);

        $formUpdated = [];
        foreach ($formProducts as $productId => $quantity) {
            $formUpdated[] = ['product_id' => $productId, 'actual_quantity' => $quantity];
        }
        $this->supplyOrderService->compareProducts($supplyOrder, $formUpdated);

        $this->tgBot->answerCbQuery(['text' => '✅ SupplyOrder compared successfully.']);

        $message = "<b>✅ SupplyOrder compared</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }

    public function cancelCompare($supplyOrderId): void
    {
        $this->tgBot->answerCbQuery(['text' => 'Operation cancelled.']);
        $this->handler->resetCache();
        $this->handler->setCacheArray('compareSupplyForm', []);

        $supplyOrder = $this->getSupplyOrder($supplyOrderId);
        $message = "<b>SupplyOrder waiting for StockManager approval</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => 'Compare products', 'callback_data' => "compareSupplyOrder:$supplyOrder->id"]]
            ]),
        ]);
    }

    protected function getCompareMsg(?SupplyOrder $supplyOrder = null, $prompt = null): string
    {
        if (!$supplyOrder) {
            $supplyOrder = $this->getSupplyOrder();
        }

        $form = $this->handler->getCacheArray('compareSupplyForm');
        $formProducts = $form['products'] ?? [];

        $message = TgMessageService::getSupplyOrderMsg($supplyOrder, false);

        $message .= "\nProducts:\n";
        foreach ($supplyOrder->products as $index => $productItem) {
            $actualQty = $formProducts[$productItem->product_id] ?? 0;
            if ($actualQty <= 0) {
                $actualQty = $productItem->actual_quantity;
            }

            $index++;
            $message .= "$index) <b>{$productItem->product->catName}</b>: <b>$actualQty {$productItem->product->getMeasureUnit()->getLabel()}</b>\n";
        }

        if ($prompt) {
            $message .= "\n<b>$prompt</b>";
        }

        return $message;
    }

    protected function getSupplyOrder($supplyOrderId = null): ?SupplyOrder
    {
        $form = $this->handler->getCacheArray('compareSupplyForm');
        $supplyOrderId = $supplyOrderId ?: ($form['id'] ?? null);

        /** @var ?SupplyOrder $supplyOrder */
        $supplyOrder = SupplyOrder::query()->find($supplyOrderId);
        return $supplyOrder;
    }

    protected function getInlineButtons(SupplyOrder $supplyOrder = null, $withSaveBtn = false): array
    {
        if (!$supplyOrder) {
            $supplyOrder = $this->getSupplyOrder();
        }

        $buttons = [];
        foreach ($supplyOrder->products as $productItem) {
            $buttons[] = [
                ['text' => $productItem->product->catName, 'callback_data' => "selectProduct:$productItem->product_id"]
            ];
        }

        if ($withSaveBtn) {
            $submitButtons = [
                [
                    ['text' => '🚫 Cancel', 'callback_data' => "cancelCompare:$supplyOrder->id"],
                    ['text' => '✅ Save', 'callback_data' => "saveCompare:$supplyOrder->id"]
                ]
            ];
        } else {
            $submitButtons = [
                [['text' => '🚫 Cancel', 'callback_data' => "cancelCompare:$supplyOrder->id"]]
            ];
        }

        return TelegramService::getInlineKeyboard([
            ...$buttons,
            ...$submitButtons
        ]);
    }
}
