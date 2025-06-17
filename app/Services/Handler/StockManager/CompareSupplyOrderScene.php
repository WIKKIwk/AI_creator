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
        'compare_selectProduct' => 'compare_selectProduct',
        'compare_inputQuantity' => 'compare_inputQuantity',
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
            case self::states['compare_inputQuantity']:
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
        $this->handler->setState(self::states['compare_selectProduct']);

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

        $this->handler->setState(self::states['compare_inputQuantity']);

        dump($form, $this->handler->getState());

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getCompareMsg(prompt: __('telegram.input_product_quantity')),
            'parse_mode' => 'HTML',
            'reply_markup' => $this->getInlineButtons(),
        ]);
    }

    public function inputQuantity($quantity): void
    {
        $form = $this->handler->getCacheArray('compareSupplyForm');
        $currentProductId = $form['currentProduct'] ?? null;

        if (!is_numeric($quantity) || (int)$quantity <= 0) {
            $message = "<i>" . __('invalid_quantity_format') ."</i>\n\n";
            $message .= $this->getCompareMsg(prompt: __('telegram.input_product_quantity'));

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
        $this->handler->setState(self::states['compare_selectProduct']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getCompareMsg(prompt: __('telegram.select_product_for_compare')),
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

        $this->tgBot->answerCbQuery(['text' => __('telegram.supplyorder_compared_success')]);

        $message = "<b>" . __('telegram.supplyorder_compared_success') . "</b>\n\n";
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
        $this->tgBot->answerCbQuery(['text' => __('telegram.operation_cancelled')]);
        $this->handler->resetCache();
        $this->handler->setCacheArray('compareSupplyForm', []);

        $supplyOrder = $this->getSupplyOrder($supplyOrderId);
        $message = "<b>" . __('telegram.supplyorder_waiting_approval') . "</b>\n\n";
        $message .= TgMessageService::getSupplyOrderMsg($supplyOrder);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => __('telegram.compare_products'), 'callback_data' => "compareSupplyOrder:$supplyOrder->id"]]
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

        $message .= "\n" . __('telegram.products') . ":\n";
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
                    ['text' => __('telegram.cancel'), 'callback_data' => "cancelCompare:$supplyOrder->id"],
                    ['text' => __('telegram.save'), 'callback_data' => "saveCompare:$supplyOrder->id"]
                ]
            ];
        } else {
            $submitButtons = [
                [['text' => __('telegram.cancel'), 'callback_data' => "cancelCompare:$supplyOrder->id"]]
            ];
        }

        return TelegramService::getInlineKeyboard([
            ...$buttons,
            ...$submitButtons
        ]);
    }
}
