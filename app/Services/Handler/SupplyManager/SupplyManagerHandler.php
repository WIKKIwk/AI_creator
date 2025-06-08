<?php

namespace App\Services\Handler\SupplyManager;

use App\Models\SupplyOrder\SupplyOrder;
use App\Models\User;
use App\Services\Handler\BaseHandler;
use App\Services\TelegramService;

class SupplyManagerHandler extends BaseHandler
{
    public User $user;
    protected array $promises = [];

    protected array $sceneHandlers = [
        'createSupplyOrder' => CreateSupplyOrderScene::class,
    ];

    protected array $callbackHandlers = [
        'confirmSupplyOrder' => [CreateSupplyOrderScene::class, 'confirmSupplyOrder'],
        'closeSupplyOrder' => [CreateSupplyOrderScene::class, 'closeSupplyOrder'],

        'confirmListOrder' => [SupplyOrderListCb::class, 'confirmOrder'],
        'supplyOrdersList' => [SupplyOrderListCb::class, 'sendList'],
        'supplyOrderPrev' => [SupplyOrderListCb::class, 'prev'],
        'supplyOrderNext' => [SupplyOrderListCb::class, 'next'],
    ];

    public const templates = [
        'supplyOrderForm' => <<<HTML
{errorMsg}

{details}
{prompt}
HTML,
    ];

    public function getMainKb(): array
    {
        return TelegramService::getInlineKeyboard([
            [['text' => '🔍 Search order', 'callback_data' => 'searchSupplyOrder']],
            [['text' => '➕ Create SupplyOrder', 'callback_data' => 'createSupplyOrder']],
            [['text' => '📋 SupplyOrders List', 'callback_data' => 'supplyOrdersList']]
        ]);
    }
}
