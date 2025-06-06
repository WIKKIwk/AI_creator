<?php

namespace App\Services\Handler\SupplyManager;

use App\Enums\ProductType;
use App\Listeners\SupplyOrderNotification;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\Warehouse;
use App\Services\Cache\Cache;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\SupplyOrderService;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

class CreateSupplyOrderScene implements SceneHandlerInterface
{
    protected TgBot $tgBot;
    protected Cache $cache;

    protected SupplyOrderService $supplyOrderService;

    public const states = [
        'supplyOrder_selectWarehouse' => 'supplyOrder_selectWarehouse',
        'supplyOrder_selectCategory' => 'supplyOrder_selectCategory',
        'supplyOrder_selectSupplier' => 'supplyOrder_selectSupplier',
        'supplyOrder_products' => 'supplyOrder_products',
        'supplyOrder_selectProduct' => 'supplyOrder_selectProduct',
        'supplyOrder_inputQuantity' => 'supplyOrder_inputQuantity',
        'supplyOrder_inputPrice' => 'supplyOrder_inputPrice',
    ];

    public function __construct(protected SupplyManagerHandler $handler)
    {
        $this->tgBot = $handler->tgBot;
        $this->cache = $handler->cache;

        $this->supplyOrderService = app(SupplyOrderService::class);
    }

    public function confirmSupplyOrder($id): void
    {
        /** @var SupplyOrder $supplyOrder */
        $supplyOrder = SupplyOrder::query()->find($id);
        if (!$supplyOrder) {
            $this->tgBot->answerCbQuery(['text' => '❌ Supply order not found!'], true);
            return;
        }

        $supplyOrder->confirm();

        $message = "<b>✅ Order confirmed!</b>\n\n";;
        $message .= SupplyOrderNotification::getSupplyOrderMsg($supplyOrder);

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
            case self::states['supplyOrder_inputQuantity']:
                $this->inputQuantity($text);
                return;
            case self::states['supplyOrder_inputPrice']:
                $this->inputPrice($text);
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
        $this->tgBot->answerCbQuery();
        $this->handler->setState(self::states['supplyOrder_selectWarehouse']);

        $buttons = Warehouse::query()->get()->map(function (Warehouse $warehouse) {
            return [['text' => $warehouse->name, 'callback_data' => "selectWarehouse:$warehouse->id"]];
        })->toArray();

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getSupplyOrderPrompt('Select warehouse:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                ...$buttons,
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']],
            ]),
        ]);

        $this->handler->setCache('edit_msg_id', $this->tgBot->getMessageId());
    }

    public function selectWarehouse($warehouseId): void
    {
        dump("Warehouse: $warehouseId");
        $this->tgBot->answerCbQuery();

        $form = $this->handler->getCacheArray('supplyOrderForm');
        $form['warehouse_id'] = $warehouseId;
        $this->handler->setCacheArray('supplyOrderForm', $form);

        $this->handler->setState(self::states['supplyOrder_selectCategory']);

        $buttons = ProductCategory::query()->get()->map(fn(ProductCategory $prodCategory) => [
            [
                'text' => $prodCategory->name,
                'callback_data' => "selectCategory:$prodCategory->id"
            ]
        ])->toArray();

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getSupplyOrderPrompt('Select category:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                ...$buttons,
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']],
            ]),
        ]);
    }

    public function selectCategory($categoryId): void
    {
        dump("Category: $categoryId");
        $this->tgBot->answerCbQuery();

        $form = $this->handler->getCacheArray('supplyOrderForm');
        $form['category_id'] = $categoryId;
        $this->handler->setCacheArray('supplyOrderForm', $form);

        $this->handler->setState(self::states['supplyOrder_selectSupplier']);

        $buttons = Organization::query()->whereNot('id', $this->handler->user->organization_id)->get()->map(
            fn(Organization $organization) => [
                ['text' => $organization->name, 'callback_data' => "selectSupplier:$organization->id"]
            ]
        )->toArray();

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getSupplyOrderPrompt('Select supplier:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                ...$buttons,
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']],
            ]),
        ]);
    }

    public function selectSupplier($supplierId): void
    {
        dump("Supplier: $supplierId");
        $this->tgBot->answerCbQuery();

        $form = $this->handler->getCacheArray('supplyOrderForm');
        $form['supplier_id'] = $supplierId;
        $this->handler->setCacheArray('supplyOrderForm', $form);

        $this->handler->setState(self::states['supplyOrder_products']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->tgBot->getMessageId(),
            'text' => $this->getSupplyOrderPrompt('Select product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [$this->getProductsButton()],
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']]
            ]),
        ]);
    }

    public function selectProduct($productId): void
    {
        dump("Product ID: $productId");

        $form = $this->handler->getCacheArray('supplyOrderForm');
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
                    'text' => $this->getSupplyOrderPrompt('Select product:', '<i>❌ Product already added.</i>'),
                    'parse_mode' => 'HTML',
                    'reply_markup' => TelegramService::getInlineKeyboard([
                        [$this->getProductsButton()],
                        [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']]
                    ]),
                ]);
                return;
            }
        }

        // Add product to the order
        $products[] = ['product_id' => $productId, 'quantity' => 0, 'offer_price' => 0];
        $form['products'] = $products;
        $this->handler->setCacheArray('supplyOrderForm', $form);

        // Ask for quantity
        $this->handler->setState(self::states['supplyOrder_inputQuantity']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getSupplyOrderPrompt('Input quantity for product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']]
            ]),
        ]);
    }

    public function inputQuantity($quantity): void
    {
        dump("Quantity: $quantity");

        if (!is_numeric($quantity) || $quantity <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getSupplyOrderPrompt('Input quantity for product:', '<i>❌ Invalid quantity.</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']]
                ]),
            ]);
            return;
        }

        $form = $this->handler->getCacheArray('supplyOrderForm');
        $products = $form['products'] ?? [];
        $lastProductIndex = count($products) - 1;

        // Update last product's quantity
        if (isset($products[$lastProductIndex])) {
            $products[$lastProductIndex]['expected_quantity'] = (float)$quantity;
            $form['products'] = $products;
            $this->handler->setCacheArray('supplyOrderForm', $form);
        }

        // Ask for offer price
        $this->handler->setState(self::states['supplyOrder_inputPrice']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getSupplyOrderPrompt('Input offer price for product:'),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']]
            ]),
        ]);
    }

    public function inputPrice($offerPrice): void
    {
        dump("Offer Price: $offerPrice");

        if (!is_numeric($offerPrice) || $offerPrice <= 0) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->handler->getCache('edit_msg_id'),
                'text' => $this->getSupplyOrderPrompt('Input offer price for product:', '<i>❌ Invalid price.</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']]
                ]),
            ]);
            return;
        }

        $form = $this->handler->getCacheArray('supplyOrderForm');
        $products = $form['products'] ?? [];
        $lastProductIndex = count($products) - 1;

        // Update last product's offer price
        if (isset($products[$lastProductIndex])) {
            $products[$lastProductIndex]['price'] = (float)$offerPrice;
            $form['products'] = $products;
            $this->handler->setCacheArray('supplyOrderForm', $form);
        }

        $this->handler->setState(self::states['supplyOrder_products']);

        $this->tgBot->sendRequestAsync('editMessageText', [
            'chat_id' => $this->tgBot->chatId,
            'message_id' => $this->handler->getCache('edit_msg_id'),
            'text' => $this->getSupplyOrderPrompt(
                'Product added successfully! You can add more products or save SupplyOrder.'
            ),
            'parse_mode' => 'HTML',
            'reply_markup' => TelegramService::getInlineKeyboard([
                [$this->getProductsButton()],
                [
                    ['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder'],
                    ['text' => '✅ Save', 'callback_data' => 'saveSupplyOrder']
                ]
            ]),
        ]);
    }

    public function saveSupplyOrder(): void
    {
        $form = $this->handler->getCacheArray('supplyOrderForm');

        if (empty($form['products'])) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $this->getSupplyOrderPrompt('', '<i>❌ No products added to the SupplyOrder.</i>'),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [$this->getProductsButton()],
                    [['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder']]
                ]),
            ]);
            return;
        }

        try {
            $supplyOrder = $this->supplyOrderService->createOrderByForm([
                'warehouse_id' => $form['warehouse_id'],
                'product_category_id' => $form['category_id'],
                'supplier_organization_id' => $form['supplier_id'],
                'products' => $form['products'],
            ]);

            $message = "<b>✅ SupplyOrder saved</b>\n\n";
            $message .= SupplyOrderNotification::getSupplyOrderMsg($supplyOrder);

            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [['text' => 'Confirm order', 'callback_data' => "confirmSupplyOrder:$supplyOrder->id"]]
                ])
            ]);

            $this->cancelSupplyOder(false);

            $this->tgBot->answerCbQuery(['text' => '✅ SupplyOrder saved successfully!'], true);

            $this->handler->sendMainMenu();
        } catch (Throwable $e) {
            $this->tgBot->sendRequestAsync('editMessageText', [
                'chat_id' => $this->tgBot->chatId,
                'message_id' => $this->tgBot->getMessageId(),
                'text' => $this->getSupplyOrderPrompt('', "<i>❌ Error saving SupplyOrder: {$e->getMessage()}</i>"),
                'parse_mode' => 'HTML',
                'reply_markup' => TelegramService::getInlineKeyboard([
                    [$this->getProductsButton()],
                    [
                        ['text' => '🚫 Cancel', 'callback_data' => 'cancelSupplyOder'],
                        ['text' => '✅ Save again', 'callback_data' => 'saveSupplyOrder']
                    ]
                ]),
            ]);
        }
    }

    public function cancelSupplyOder($withResponse = true): void
    {
        $this->handler->forgetCache('supplyOrderForm');
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
        $query = $inlineQuery['query'] ?? '';
        dump("query: $query");

        $explode = explode(' ', $query);
        $type = Arr::get($explode, 0);
        $search = Arr::get($explode, 1);

        switch (true) {
            case str_starts_with($type, 'category:'):
                $categoryId = (int)str_replace('category:', '', $type);
                /** @var Collection<Product> $products */
                $products = Product::query()
                    ->search($search)
                    ->where('product_category_id', $categoryId)
                    ->take(30)
                    ->get();
                break;

            default:
                $search = $query ?: null;

                /** @var Collection<Product> $products */
                $products = Product::query()
                    ->search($search)
                    ->whereNot('type', ProductType::SemiFinishedProduct->value)
                    ->take(30)
                    ->get();
        }

        $this->tgBot->sendRequest('answerInlineQuery', [
            'inline_query_id' => $inlineQuery['id'],
            'results' => $this->getProductInlineResult($products),
            'cache_time' => 0,
        ]);
    }

    protected function getProductInlineResult($products): array
    {
        $results = [];
        /** @var Collection<Product> $products */
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

        return $results;
    }

    protected function getSupplyOrderPrompt(string $prompt, $errorMsg = null): string
    {
        return strtr($this->handler::templates['supplyOrderForm'], [
            '{errorMsg}' => $errorMsg ?? '',
            '{details}' => $this->getSupplyOrderDetails(),
            '{prompt}' => $prompt,
        ]);
    }

    protected function getSupplyOrderDetails(): string
    {
        $form = $this->handler->getCacheArray('supplyOrderForm');

        $warehouse = isset($form['warehouse_id']) ? Warehouse::query()->find($form['warehouse_id']) : null;
        $warehouseName = $warehouse?->name ?? '-';

        $category = isset($form['category_id']) ? ProductCategory::query()->find($form['category_id']) : null;
        $categoryName = $category?->name ?? '-';

        $supplier = isset($form['supplier_id']) ? Organization::query()->find($form['supplier_id']) : null;
        $supplierName = $supplier?->name ?? '-';

        $products = $form['products'] ?? [];

        $result = "<b>Supply order details:</b>\n\n";
        $result .= "Warehouse: <b>$warehouseName</b>\n";
        $result .= "Category: <b>$categoryName</b>\n";
        $result .= "Supplier: <b>$supplierName</b>\n";
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

                $productName = $product->catName ?? '-';
                $quantity = $productItem['expected_quantity'] ?? '-';
                $offerPrice = $productItem['price'] ?? '-';

                $result .= "$index) <b>$productName</b>\n";
                $result .= "Quantity: <b>$quantity {$product->getMeasureUnit()->getLabel()}</b>\n";
                $result .= "Price: <b>$offerPrice</b>\n\n";
            }
        }

        return $result;
    }

    protected function getProductsButton(): array
    {
        $form = $this->handler->getCacheArray('supplyOrderForm');
        $categoryId = $form['category_id'] ?? 0;
        return ['text' => '🔍 Select Product', 'switch_inline_query_current_chat' => "category:$categoryId"];
    }
}
