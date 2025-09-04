<?php

namespace App\Services;

use App\Models\Inventory\Inventory;
use App\Models\Inventory\InventoryTransaction;
use App\Models\ProdOrder\ProdOrder;
use App\Models\SupplyOrder\SupplyOrder;
use Illuminate\Support\Carbon;

class AiContextService
{
    public function summary(): array
    {
        $now = now();
        $last7 = $now->copy()->subDays(7);

        // Minimal, safe aggregates (scoped by model scopes/auth)
        $inventoryCount = Inventory::query()->count();
        $recentTxCount = InventoryTransaction::query()->where('created_at', '>=', $last7)->count();
        $openProdOrders = ProdOrder::query()->whereNotNull('id')->whereNull('confirmed_at')->count();
        $openSupplyOrders = SupplyOrder::query()->whereNull('closed_at')->count();

        return [
            'timestamp' => $now->toIso8601String(),
            'inventory_items' => $inventoryCount,
            'recent_transactions_7d' => $recentTxCount,
            'open_prod_orders' => $openProdOrders,
            'open_supply_orders' => $openSupplyOrders,
        ];
    }
}

