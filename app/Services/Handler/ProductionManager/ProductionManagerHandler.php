<?php

namespace App\Services\Handler\ProductionManager;

use App\Enums\ProductType;
use App\Listeners\ProdOrderNotification;
use App\Models\Product;
use App\Models\User;
use App\Services\Handler\BaseHandler;
use App\Services\TelegramService;
use Illuminate\Database\Eloquent\Collection;

class ProductionManagerHandler extends BaseHandler
{
    public User $user;
    protected array $promises = [];
    protected array $sceneHandlers = [
        'createProdOrder' => CreateProdOrderScene::class,
    ];

    protected array $callbackRegistry = [
        'confirmProdOrder' => [CreateProdOrderScene::class, 'confirmProdOrder'],

        'confirmListOrder' => [ProdOrderListCb::class, 'confirmOrder'],
        'prodOrdersList' => [ProdOrderListCb::class, 'sendList'],
        'prodOrderPrev' => [ProdOrderListCb::class, 'prev'],
        'prodOrderNext' => [ProdOrderListCb::class, 'next'],
    ];

    public const templates = [
        'prodOrderGroup' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
    ];

    public function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [['text' => '➕ Create ProdOrder', 'callback_data' => 'createProdOrder']],
            [['text' => '📋 ProdOrders List', 'callback_data' => 'prodOrdersList']]
        ]);
    }
}
