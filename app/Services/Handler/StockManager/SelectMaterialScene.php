<?php

namespace App\Services\Handler\StockManager;

use App\Listeners\ProdOrderNotification;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use App\Services\TgMessageService;
use Illuminate\Support\Arr;

class SelectMaterialScene implements SceneHandlerInterface
{
    public TgBot $tgBot;
    public Cache $cache;
    public ProdOrderService $prodOrderService;

    public const states = [
        'material_changeAvailable' => 'material_changeAvailable',
    ];

    public function __construct(public StockManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function handleText($text): void
    {
        $activeState = $this->handler->getState();
        switch ($activeState) {
            case self::states['material_changeAvailable']:
                $this->inputAvailableQty($text);
                return;
        }

        $this->tgBot->rmLastMsg();
    }

    public function handleScene($params = []): void
    {
        dump("Scene: " . $this->handler->getScene());
        $this->tgBot->answerCbQuery();
        $materialId = $params[0];
        $material = $this->getMaterial($materialId);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => TgMessageService::getMaterialMsg($material),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => __('telegram.change_available'), 'callback_data' => "changeAvailable:$material->id"]],
                [['text' => __('telegram.back'), 'callback_data' => "cancelMaterial:$material->prod_order_step_id"]],
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

        $message = TgMessageService::getMaterialMsg($material);
        $message .= "\n\n" . __('telegram.input_available_quantity');

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => __('telegram.back'), 'callback_data' => "cancelMaterial:$material->prod_order_step_id"]],
            ]),
        ]);
    }

    public function inputAvailableQty($quantity): void
    {
        $this->tgBot->rmLastMsg();
        $form = $this->handler->getCacheArray('materialForm');
        if (empty($form)) {
            $this->handler->sendMainMenu();
            return;
        }

        $material = $this->getMaterial($form['id']);

        if (!is_numeric($quantity) || $quantity <= 0) {
            $message = "<i>❌ " . __('telegram.invalid_quantity_format') . "</i>\n\n";
            $message .= TgMessageService::getMaterialMsg($material);
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $message . "\n\n" . __('telegram.input_available_quantity'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => __('telegram.back'), 'callback_data' => "cancelMaterial:$material->prod_order_step_id"]],
                ]),
            ]);
            return;
        }

        $form['available_quantity'] = $quantity;
        $material->available_quantity = $quantity;
        $this->handler->setCacheArray('materialForm', $form);

        $message = TgMessageService::getMaterialMsg($material);
        $message .= "\n\n" . __('telegram.input_available_quantity');

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => __('telegram.back'), 'callback_data' => "cancelMaterial:$material->prod_order_step_id"],
                    ['text' => __('telegram.save'), 'callback_data' => "saveMaterial:$material->id"],
                ],
            ]),
        ]);
    }

    public function saveMaterial($materialId): void
    {
        $material = $this->getMaterial($materialId);

        $form = $this->handler->getCacheArray('materialForm');
        $availableQuantity = $form['available_quantity'];

        $insufficientAssets = $this->prodOrderService->checkMaterialsExact(
            $material->step,
            $material->product_id,
            $availableQuantity
        );
        if (!empty($insufficientAssets)) {
            $this->tgBot->answerCbQuery(['text' => __('telegram.insufficient_assets')]);

            $message = "<i>" . __('telegram.insufficient_assets') . "</i>\n\n";

            foreach ($insufficientAssets as $missingAssets) {
                foreach ($missingAssets as $item) {
                    $product = Arr::get($item, 'product');
                    $qty = Arr::get($item, 'quantity');
                    $category = Arr::get($item, 'category');
                    $measureUnit = Arr::get($item, 'measure_unit');

                    $message .= "<b>$category {$product['name']}: $qty $measureUnit</b>\n";
                }
            }

            $message .= "\n<b>" . __('telegram.create_supply_orders_question') . "</b>";

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [
                        ['text' => __('telegram.back'), 'callback_data' => "cancelMaterial:$material->prod_order_step_id"],
                        ['text' => __('telegram.confirm'), 'callback_data' => "confirmSupplyOrder:$material->id"]
                    ],
                ]),
            ]);
            return;
        }

        $this->prodOrderService->updateMaterialsExact(
            $material->step,
            $material->product_id,
            $availableQuantity
        );
        $material->available_quantity = $availableQuantity;

        $this->tgBot->answerCbQuery(['text' => __('telegram.material_changed_success')]);

        $message = "<b>" . __('telegram.material_changed_success') . "</b>\n\n";
        $message .= TgMessageService::getMaterialMsg($material);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => __('telegram.back'), 'callback_data' => "cancelMaterial:$material->prod_order_step_id"]],
            ]),
        ]);

        // Clear the cache after saving
        $this->handler->setCacheArray('executionForm', []);
    }

    public function confirmSupplyOrder($materialId): void
    {
        $material = $this->getMaterial($materialId);

        $form = $this->handler->getCacheArray('materialForm');
        $availableQuantity = $form['available_quantity'];

        $this->prodOrderService->updateMaterialsExact(
            $material->step,
            $material->product_id,
            $availableQuantity
        );
        $material->available_quantity = $availableQuantity;

        $this->tgBot->answerCbQuery(['text' => __('telegram.supply_orders_created')]);

        $message = "<b>" . __('telegram.supply_orders_created') . "</b>\n\n";
        $message .= TgMessageService::getMaterialMsg($material);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => __('telegram.back'), 'callback_data' => "cancelMaterial:$material->prod_order_step_id"]],
            ]),
        ]);
    }

    public function cancelMaterial($stepId): void
    {
        $this->handler->resetCache();
        $this->handler->materialsList($stepId);
    }

    public function getMaterial($materialId): ?ProdOrderStepProduct
    {
        /** @var ?ProdOrderStepProduct $material */
        $material = ProdOrderStepProduct::query()->find($materialId);
        return $material;
    }
}
