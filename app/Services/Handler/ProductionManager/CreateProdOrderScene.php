<?php

namespace App\Services\Handler\ProductionManager;

use App\Enums\ProdOrderGroupType;
use App\Enums\ProductType;
use App\Listeners\ProdOrderNotification;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\ProdOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use Throwable;

class CreateProdOrderScene implements SceneHandlerInterface
{
    protected TgBot $tgBot;
    protected Cache $cache;

    protected ProdOrderService $prodOrderService;

    public const states = [
        'prodOrder_selectType' => 'prodOrder_selectType',
        'prodOrder_inputWarehouse' => 'prodOrder_inputWarehouse',
        'prodOrder_inputAgent' => 'prodOrder_inputAgent',
        'prodOrder_inputDeadline' => 'prodOrder_inputDeadline',
        'prodOrder_products' => 'prodOrder_products',
        'prodOrder_selectProduct' => 'prodOrder_selectProduct',
        'prodOrder_inputQuantity' => 'prodOrder_inputQuantity',
        'prodOrder_inputOfferPrice' => 'prodOrder_inputOfferPrice',
    ];

    public function __construct(protected ProductionManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->prodOrderService = app(ProdOrderService::class);
    }

    public function confirmProdOrder($id): void
    {
        $poGroup = $this->prodOrderService->getOrderGroupById($id);
        $poGroup->confirm();

        $message = "<b>✅ Order confirmed!</b>\n\n";
        $message .= ProdOrderNotification::getProdOrderMsg($poGroup);

        $this->tgBot->answerCbQuery(['text' => '✅ Order confirmed!'], true);
        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }

    public function handleText($text): void
    {
        $activeState = $this->handler->getState();

        switch ($activeState) {
            case self::states['prodOrder_inputDeadline']:
                $this->inputDeadlineText($text);
                return;
            case self::states['prodOrder_inputQuantity']:
                $this->inputQuantityText($text);
                return;
            case self::states['prodOrder_inputOfferPrice']:
                $this->inputOfferPriceText($text);
                return;
        }

        if (str_starts_with($text, '/select_product')) {
            $productId = trim(str_replace('/select_product', '', $text));
            $this->selectProduct($productId);
            return;
        }

        if ($activeState) {
            $this->tgBot->rmLastMsg();
            return;
        }

        $this->handler->sendMainMenu();
    }

    public function handleScene(): void
    {
        $this->handler->setState(self::states['prodOrder_selectType']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getProdOrderPrompt('Select type of ProdOrder:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [
                    ['text' => 'By order', 'callback_data' => 'selectType:1'],
                    ['text' => 'By catalog', 'callback_data' => 'selectType:2']
                ],
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
            ]),
        ]);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
    }

    public function selectType($type): void
    {
        dump("Type: $type");
        $this->tgBot->answerCbQuery();

        $form = $this->handler->getCacheArray('prodOrderForm');
        $form['type'] = $type;
        $this->handler->setCacheArray('prodOrderForm', $form);

        $this->handler->setState(self::states['prodOrder_inputWarehouse']);

        $buttons = Warehouse::query()->get()->map(function (Warehouse $warehouse) {
            return [['text' => $warehouse->name, 'callback_data' => "selectWarehouse:$warehouse->id"]];
        })->toArray();

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getProdOrderPrompt('Select warehouse for ProdOrder:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                ...$buttons,
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']],
            ]),
        ]);
    }

    public function selectWarehouse($warehouseId): void
    {
        dump("Warehouse: $warehouseId");
        $this->tgBot->answerCbQuery();

        $form = $this->handler->getCacheArray('prodOrderForm');
        $form['warehouse_id'] = $warehouseId;
        $this->handler->setCacheArray('prodOrderForm', $form);
        $this->handler->setCacheArray('prodOrderForm', $form);

        $buttons = [];
        $type = $form['type'] ?? null;
        if ($type == 1) {
            $this->handler->setState(self::states['prodOrder_inputAgent']);
            $prompt = 'Select agent for ProdOrder:';
            $buttons = Organization::query()->whereNot('id', $this->handler->user->organization_id)->get()->map(
                fn(Organization $organization) => [
                    ['text' => $organization->name, 'callback_data' => "selectAgent:$organization->id"]
                ]
            )->toArray();
        } else {
            $this->handler->setState(self::states['prodOrder_inputDeadline']);
            $prompt = 'Input deadline for ProdOrder (YYYY-MM-DD):';
        }

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getProdOrderPrompt($prompt),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                ...$buttons,
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
            ]),
        ]);
    }

    public function selectAgent($agentId): void
    {
        dump("Agent: $agentId");
        $this->tgBot->answerCbQuery();

        $form = $this->handler->getCacheArray('prodOrderForm');
        $form['organization_id'] = $agentId;
        $this->handler->setCacheArray('prodOrderForm', $form);

        $this->handler->setState(self::states['prodOrder_products']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getProdOrderPrompt('Select product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🔍 Select Product', 'switch_inline_query_current_chat' => '']],
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
            ]),
        ]);
    }

    public function inputDeadlineText($deadline): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getProdOrderPrompt(
                    'Input deadline for ProdOrder (YYYY-MM-DD):',
                    '<i>❌ Invalid date format.</i>'
                ),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
                ]),
            ]);
            return;
        }

        dump("Deadline: $deadline");

        $form = $this->handler->getCacheArray('prodOrderForm');
        $form['deadline'] = $deadline;
        $this->handler->setCacheArray('prodOrderForm', $form);

        $this->handler->setState(self::states['prodOrder_products']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getProdOrderPrompt('Select product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🔍 Search Product', 'switch_inline_query_current_chat' => '']],
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
            ]),
        ]);
    }

    public function selectProduct($productId): void
    {
        dump("Product ID: $productId");

        $form = $this->handler->getCacheArray('prodOrderForm');
        if (empty($form)) {
            return;
        }

        $products = $form['products'] ?? [];

        // Check if product already exists in the order
        foreach ($products as $productItem) {
            if ($productItem['product_id'] == $productId) {
                $this->tgBot->sendRequestAsync('editMessageText', [
                    'chat_id' => $this->tgBot->chatId,
                    'message_id' => $this->tgBot->getMessageId(),
                    'text' => $this->getProdOrderPrompt('Select product:', '<i>❌ Product already added.</i>'),
                    'parse_mode' => 'HTML',
                    'reply_markup' => TelegramService::getInlineKeyboard([
                        [['text' => '🔍 Search Product', 'switch_inline_query_current_chat' => '']],
                        [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
                    ]),
                ]);
                return;
            }
        }

        // Add product to the order
        $products[] = ['product_id' => $productId, 'quantity' => 0, 'offer_price' => 0];
        $form['products'] = $products;
        $this->handler->setCacheArray('prodOrderForm', $form);

        // Ask for quantity
        $this->handler->setState(self::states['prodOrder_inputQuantity']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getProdOrderPrompt('Input quantity for product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
            ]),
        ]);
    }

    public function inputQuantityText($quantity): void
    {
        dump("Quantity: $quantity");

        if (!is_numeric($quantity) || $quantity <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getProdOrderPrompt('Input quantity for product:', '<i>❌ Invalid quantity.</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
                ]),
            ]);
            return;
        }

        $form = $this->handler->getCacheArray('prodOrderForm');
        $products = $form['products'] ?? [];
        $lastProductIndex = count($products) - 1;

        // Update last product's quantity
        if (isset($products[$lastProductIndex])) {
            $products[$lastProductIndex]['quantity'] = (float)$quantity;
            $form['products'] = $products;
            $this->handler->setCacheArray('prodOrderForm', $form);
        }

        // Ask for offer price
        $this->handler->setState(self::states['prodOrder_inputOfferPrice']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getProdOrderPrompt('Input offer price for product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
            ]),
        ]);
    }

    public function inputOfferPriceText($offerPrice): void
    {
        dump("Offer Price: $offerPrice");

        if (!is_numeric($offerPrice) || $offerPrice <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getProdOrderPrompt('Input offer price for product:', '<i>❌ Invalid price.</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
                ]),
            ]);
            return;
        }

        $form = $this->handler->getCacheArray('prodOrderForm');
        $products = $form['products'] ?? [];
        $lastProductIndex = count($products) - 1;

        // Update last product's offer price
        if (isset($products[$lastProductIndex])) {
            $products[$lastProductIndex]['offer_price'] = (float)$offerPrice;
            $form['products'] = $products;
            $this->handler->setCacheArray('prodOrderForm', $form);
        }

        $this->handler->setState(self::states['prodOrder_products']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getProdOrderPrompt(
                'Product added successfully! You can add more products or save ProdOrder.'
            ),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🔍 Select Product', 'switch_inline_query_current_chat' => '']],
                [
                    ['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder'],
                    ['text' => '✅ Save', 'callback_data' => 'saveProdOrder']
                ]
            ]),
        ]);
    }

    public function saveProdOrder(): void
    {
        $form = $this->handler->getCacheArray('prodOrderForm');
        dump($form);

        if (empty($form['products'])) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $this->getProdOrderPrompt('', '<i>❌ No products added to the ProdOrder.</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🔍 Select Product', 'switch_inline_query_current_chat' => '']],
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder']]
                ]),
            ]);
            return;
        }

        try {
            $poGroup = $this->prodOrderService->createOrderByForm($form);

            $message = "<b✅ Execution saved successfully!>✅ ProdOrder saved</b>\n\n";
            $message .= ProdOrderNotification::getProdOrderMsg($poGroup);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => 'Confirm order', 'callback_data' => "confirmProdOrder:$poGroup->id"]]
                ])
            ]);

            $this->cancelProdOrder(false);

            $this->tgBot->answerCbQuery(['text' => '✅ ProdOrder saved successfully!'], true);

            $this->handler->sendMainMenu();
        } catch (Throwable $e) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $this->getProdOrderPrompt('', "<i>❌ Error saving ProdOrder: {$e->getMessage()}</i>"),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🔍 Select Product', 'switch_inline_query_current_chat' => '']],
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelProdOrder'],
                        ['text' => '✅ Save again', 'callback_data' => 'saveProdOrder']
                    ]
                ]),
            ]);
        }
    }

    public function cancelProdOrder($withResponse = true): void
    {
        $this->handler->forgetCache('prodOrderForm');
        $this->handler->forgetCache('state');
        $this->handler->forgetCache('edit_msg_id');

        if ($withResponse) {
            $this->tgBot->answerCbQuery(['text' => 'Operation cancelled.']);
            $this->tgBot->sendRequestAsync('deleteMessage', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
            ]);
            $this->handler->sendMainMenu();
        }
    }

    public function handleInlineQuery($inlineQuery): void
    {
        $search = $inlineQuery['query'] ?? '';

        /** @var Collection<Product> $products */
        $products = Product::query()
            ->search($search)
            ->where('type', ProductType::ReadyProduct->value)
            ->take(30)
            ->get();

        $results = [];
        foreach ($products as $product) {
            $results[] = [
                'type' => 'article',
                'id' => (string)$product->id,
                'title' => "$product->catName ($product->code)",
                'description' => $product->description ?: '',
                'input_message_content' => [
                    'message_text' => "/select_product $product->id",
                ],
            ];
        }

        dump($inlineQuery['id'], $results);

        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $inlineQuery['id'],
            'results' => $results,
            'cache_time' => 0,
        ]);
    }

    protected function getProdOrderPrompt(string $prompt, $errorMsg = null): string
    {
        return strtr($this->handler::templates['prodOrderGroup'], [
            '{errorMsg}' => $errorMsg ?? '',
            '{details}' => $this->getProdOrderDetails(),
            '{prompt}' => $prompt,
        ]);
    }

    protected function getProdOrderDetails(): string
    {
        $result = "<b>ProdOrder details:</b>\n\n";

        $form = $this->handler->getCacheArray('prodOrderForm');

        $type = $form['type'] ?? null;
        $typeName = $type ? ProdOrderGroupType::tryFrom($type)->getLabel() : '-';

        $warehouse = isset($form['warehouse_id']) ? Warehouse::query()->find($form['warehouse_id']) : null;
        $warehouseName = $warehouse?->name ?? '-';

        $agent = isset($form['organization_id']) ? Organization::query()->find($form['organization_id']) : null;
        $agentName = $agent?->name ?? '-';
        $agentName = $type == 1 ? $agentName : null;

        $deadline = $form['deadline'] ?? '-';
        $deadline = $type == 2 ? $deadline : null;

        $products = $form['products'] ?? [];

        $result .= "Type: <b>$typeName</b>\n";
        $result .= "Warehouse: <b>$warehouseName</b>\n";
        if ($agentName) {
            $result .= "Agent: <b>$agentName</b>\n";
        }
        if ($deadline) {
            $result .= "Deadline: <b>$deadline</b>\n";
        }

        $result .= "\n<b>Products List:</b>\n";
        if (!empty($products)) {
            foreach ($products as $index => $productItem) {
                $index++;
                // fill product name, quantity, and offer price
                /** @var Product $product */
                $product = Product::query()->find($productItem['product_id']);
                if (!$product) {
                    throw new RuntimeException("Product with ID {$productItem['product_id']} not found.");
                }

                $productName = $product?->catName ?? '-';
                $quantity = $productItem['quantity'] ?? '-';
                $offerPrice = $productItem['offer_price'] ?? '-';

                $result .= "$index) <b>$productName</b>\n";
                $result .= "Quantity: <b>$quantity {$product?->getMeasureUnit()->getLabel()}</b>\n";
                $result .= "Offer Price: <b>$offerPrice</b>\n\n";
            }
        }

        return $result;
    }
}
