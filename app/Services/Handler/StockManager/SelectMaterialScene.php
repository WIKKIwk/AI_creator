<?php

namespace App\Services\Handler\StockManager;

use App\Listeners\ProdOrderNotification;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;

class SelectMaterialScene implements SceneHandlerInterface
{
    protected TgBot $tgBot;
    protected Cache $cache;
    protected ProdOrderService $prodOrderService;

    protected const states = [
        'material_changeAvailable' => 'material_changeAvailable',
    ];

    public function __construct(protected StockManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleText($text): void
    {
        $activeState = $this->handler->getState();
        dump("Active state: $activeState, Text: $text");
        switch ($activeState) {
            case self::states['material_changeAvailable']:
                $this->inputAvailableQty($text);
                return;
        }

        if ($activeState) {
            $this->tgBot->rmLastMsg();
            return;
        }

        $this->handler->sendMainMenu();
    }

    public function handleScene($params = []): void
    {
        $this->tgBot->answerCbQuery();
        dump($params);
        $materialId = $params[0];
        $material = $this->getMaterial($materialId);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => ProdOrderNotification::getMaterialMsg($material),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => 'Change available', 'callback_data' => "changeAvailable:$material->id"]],
                [['text' => '⬅️ Back', 'callback_data' => "materialsList:$material->prod_order_step_id"]],
            ]),
        ]);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
    }

    public function changeAvailable($materialId): void
    {
        $this->tgBot->answerCbQuery();
        $this->handler->setCacheArray('materialForm', ['id' => $materialId, 'available_quantity' => 0]);
        $this->handler->setState(self::states['material_changeAvailable']);

        $material = $this->getMaterial($materialId);

        $message = ProdOrderNotification::getMaterialMsg($material);
        $message .= "\n\n Input available quantity for this material:";

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '⬅️ Back', 'callback_data' => "cancelMaterial"]],
            ]),
        ]);
    }

    protected function inputAvailableQty($quantity): void
    {
        $this->tgBot->rmLastMsg();
        $form = $this->handler->getCacheArray('materialForm');
        if (empty($form)) {
            $this->handler->sendMainMenu();
            return;
        }

        $material = $this->getMaterial($form['id']);

        if (!is_numeric($quantity) || $quantity <= 0) {

            $message = "<i>❌ Invalid quantity format.</i>\n\n";
            $message .= ProdOrderNotification::getMaterialMsg($material);
            $message .= "\n\n Input available quantity for this material:";

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '⬅️ Back', 'callback_data' => 'cancelMaterial']],
                ]),
            ]);
            return;
        }

        $form = $this->handler->getCacheArray('executionForm');
        $form['available_quantity'] = $quantity;
        $material->available_quantity = $quantity;
        $this->handler->setCacheArray('executionForm', $form);
dump($form);
        $message = ProdOrderNotification::getMaterialMsg($material);
        $message .= "\n\n Input available quantity for this material:";

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => '⬅️ Back', 'callback_data' => "materialsList:$material->prod_order_step_id"],
                    ['text' => '✅ Save', 'callback_data' => "saveMaterial:$material->id"],
                ],
            ]),
        ]);
    }

    protected function getMaterial($materialId): ?ProdOrderStepProduct
    {
        /** @var ?ProdOrderStepProduct $material */
        $material = ProdOrderStepProduct::query()->find($materialId);
        return $material;
    }
}
