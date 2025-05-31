<?php

namespace App\Services\Handler\ProductionManager;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\User;
use App\Services\Cache\Cache;
use App\Services\Handler\BaseHandler;
use App\Services\Handler\Interface\SceneHandlerInterface;
use App\Services\TelegramService;
use App\Services\TgBot\TgBot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductionManagerHandler extends BaseHandler
{
    public User $user;
    protected array $promises = [];

    public const scenes = [
        'createProdOrder' => 'createProdOrder',
        'prodOrdersList' => 'prodOrdersList',
    ];

    public const sceneHandlers = [
        'createProdOrder' => CreateProdOrderScene::class,
        'prodOrdersList' => ProdOrderListScene::class,
    ];

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

    public const templates = [
        'prodOrderGroup' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
    ];

    public function __construct(TgBot $tgBot, Cache $cache)
    {
        parent::__construct($tgBot, $cache);
    }

    public function validateUser(User $user): bool
    {
        return true;
    }

    public function handleStart(): void
    {
        $this->sendMainMenu();
    }

    public function handleHelp(): void
    {
        $this->tgBot->answerMsg(['text' => "What do you need help with?"]);
    }

    public function sendMainMenu($edit = false): void
    {
        $msg = self::getUserDetailsMsg($this->user);

        $params = [
            'chat_id' => $this->tgBot->chatId,
            'text' => <<<HTML
<b>Your details:</b>

$msg
HTML,
            'reply_markup' => $this->getMainKb(),
            'parse_mode' => 'HTML',
        ];

        if ($edit) {
            $params['message_id'] = $this->tgBot->getMessageId();
            $this->tgBot->sendRequestAsync('editMessageText', $params);
        } else {
            $this->tgBot->sendRequestAsync('sendMessage', $params);
        }
    }

    public function handleText(string $text): void
    {
        $activeScene = $this->getScene();
        if ($activeScene) {
            $this->getActiveSceneHandler($activeScene)?->handleText($text);
            return;
        }

        $this->sendMainMenu();
    }

    public function getActiveSceneHandler($scene = null): ?SceneHandlerInterface
    {
        return match ($scene) {
            self::scenes['createProdOrder'] => new CreateProdOrderScene($this),
            default => null,
        };
    }

    public function confirmProdOrder($id): void
    {
        (new CreateProdOrderScene($this))->confirmProdOrder($id);
    }

    public function createProdOrder(): void
    {
        $this->tgBot->answerCbQuery();
        $this->setScene(self::scenes['createProdOrder']);

        (new CreateProdOrderScene($this))->handleScene();
    }

    public function prodOrdersList(): void
    {
        $this->tgBot->answerCbQuery();
        $this->setScene(self::scenes['prodOrdersList']);

        (new ProdOrderListScene($this))->handleScene();
    }

    public function prodOrderPrev($page): void
    {
        (new ProdOrderListScene($this))->prodOrderPrev($page);
    }

    public function prodOrderNext($page): void
    {
        (new ProdOrderListScene($this))->prodOrderNext($page);
    }

    public function confirmListOrder($groupId): void
    {
        (new ProdOrderListScene($this))->confirmListOrder($groupId);
    }

    public function backMainMenu(): void
    {
        $this->tgBot->answerCbQuery();
        $this->setScene('');
        $this->sendMainMenu(true);
    }

    public function handleInlineQuery($inlineQuery): void
    {
        $search = $inlineQuery['query'] ?? '';
        $search = mb_strtolower(trim($search));

        /** @var Collection<Product> $products */
        $products = Product::query()
            ->when(!empty($search), function (Builder $builder) use ($search) {
                $builder->where(function (Builder $query) use ($search) {
                    $query->whereRaw('LOWER(name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(code) LIKE ?', ["%$search%"])
                        ->orWhereHas('category', function (Builder $query) use ($search) {
                            $query->whereRaw('LOWER(name) LIKE ?', ["%$search%"])
                                ->orWhereRaw('LOWER(code) LIKE ?', ["%$search%"]);
                        });
                });
            })
            ->whereNot('type', ProductType::SemiFinishedProduct->value)
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

    public function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [['text' => '➕ Create ProdOrder', 'callback_data' => 'createProdOrder']],
            [['text' => '📋 ProdOrders List', 'callback_data' => 'prodOrdersList']]
        ]);
    }
}
