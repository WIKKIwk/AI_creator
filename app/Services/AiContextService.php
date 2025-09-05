<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryTransaction;
use App\Models\Organization;
use App\Models\OrganizationPartner;
use App\Models\ProdOrder\ProdOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StorageLocation;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\User;
use Illuminate\Support\Carbon;

class AiContextService
{
    /**
     * Build AI context snapshot.
     * If $global = true and config('services.ai.global') is true, aggregates ignore tenant scopes.
     */
    public function summary(bool $global = false): array
    {
        $now = now();
        $last7 = $now->copy()->subDays(7);

        $isGlobal = $global && (bool) config('services.ai.global');
        $orgId = auth()->user()?->organization_id;

        // Inventory positions and total quantity
        $inventoryItemsQ = $isGlobal
            ? InventoryItem::query()->withoutGlobalScopes()
            : InventoryItem::query();
        if (!$isGlobal && $orgId) {
            $inventoryItemsQ->whereHas('inventory.warehouse', fn($q) => $q->where('organization_id', $orgId));
        }
        $inventoryQuantity = (float) $inventoryItemsQ->sum('quantity');
        $inventoryPositions = (int) $inventoryItemsQ->distinct('inventory_id')->count('inventory_id');

        // Transactions last 7d
        $txBase = $isGlobal
            ? InventoryTransaction::query()->withoutGlobalScopes()
            : InventoryTransaction::query();
        $txQ = (clone $txBase)->where('created_at', '>=', $last7);
        if (!$isGlobal && $orgId) {
            $txQ->whereHas('warehouse', fn($q) => $q->where('organization_id', $orgId));
        }
        $txIn7d = (float) (clone $txQ)->where('type', TransactionType::In)->sum('quantity');
        $txOut7d = (float) (clone $txQ)->where('type', TransactionType::Out)->sum('quantity');
        $txCount7d = (int) $txQ->count();

        // Products / Categories
        $prodQ = $isGlobal ? Product::query()->withoutGlobalScopes() : Product::query();
        $categoryQ = $isGlobal ? ProductCategory::query()->withoutGlobalScopes() : ProductCategory::query();
        if (!$isGlobal && $orgId) {
            $prodQ->whereHas('category', fn($q) => $q->where('organization_id', $orgId));
            $categoryQ->where('organization_id', $orgId);
        }
        $products = (int) $prodQ->count();
        $categories = (int) $categoryQ->count();

        // Supply Orders
        $soQ = $isGlobal ? SupplyOrder::query()->withoutGlobalScopes() : SupplyOrder::query();
        $soOpen = (clone $soQ)->whereNull('closed_at')->count();
        $soDelivered = (clone $soQ)->whereNotNull('delivered_at')->count();
        $soClosed = (clone $soQ)->whereNotNull('closed_at')->count();

        // Prod Orders
        $poQ = ProdOrder::query();
        if (!$isGlobal && $orgId) {
            $poQ->whereHas('group.warehouse', fn($q) => $q->where('organization_id', $orgId));
        }
        $poOpen = (clone $poQ)->whereNull('confirmed_at')->count();
        $poStarted = (clone $poQ)->whereNotNull('started_at')->count();
        $poCompleted = (clone $poQ)->where('status', OrderStatus::Completed->value)->count();

        // Storage locations
        $slQ = $isGlobal ? StorageLocation::query()->withoutGlobalScopes() : StorageLocation::query();
        $storageLocations = (int) $slQ->count();

        // Partners
        $partnersQ = $isGlobal ? OrganizationPartner::query()->withoutGlobalScopes() : OrganizationPartner::query();
        $partners = (int) $partnersQ->count();

        // Organizations, Warehouses, Users
        $orgCount = $isGlobal ? Organization::query()->count() : ($orgId ? 1 : Organization::query()->count());
        $warehouseQ = $isGlobal ? \App\Models\Warehouse::query()->withoutGlobalScopes() : \App\Models\Warehouse::query();
        $warehouses = (int) $warehouseQ->count();

        $userQ = $isGlobal ? User::query() : User::query()->when($orgId, fn($q) => $q->where('organization_id', $orgId));
        $usersByRole = $userQ->selectRaw('role, COUNT(*) as c')->groupBy('role')->pluck('c', 'role')->toArray();
        $usersTotal = array_sum($usersByRole);

        return [
            'timestamp' => $now->toIso8601String(),
            'scope' => $isGlobal ? 'global' : 'organization',

            'organizations' => (int) $orgCount,
            'warehouses' => $warehouses,
            'users_total' => (int) $usersTotal,
            'users_by_role' => $usersByRole,

            'categories' => $categories,
            'products' => $products,
            'partners' => $partners,
            'storage_locations' => $storageLocations,

            'inventory_positions' => (int) $inventoryPositions,
            'inventory_quantity' => $inventoryQuantity,

            'transactions_7d' => [
                'count' => $txCount7d,
                'in_qty' => $txIn7d,
                'out_qty' => $txOut7d,
            ],

            'prod_orders' => [
                'open' => $poOpen,
                'started' => $poStarted,
                'completed' => $poCompleted,
            ],

            'supply_orders' => [
                'open' => $soOpen,
                'delivered' => $soDelivered,
                'closed' => $soClosed,
            ],
        ];
    }
}
