<?php

namespace App\Console\Commands;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryTransaction;
use App\Models\Inventory\MiniInventory;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\Task;
use App\Models\WorkStation;
use Illuminate\Console\Command;

class EmptyOrders extends Command
{
    protected $signature = 'app:empty-orders';
    protected $description = 'Empty orders command';

    public function handle(): void
    {
        $workStations = WorkStation::query()->withoutGlobalScopes()->get();
        foreach ($workStations as $workStation) {
            $workStation->prod_order_id = null;
            $workStation->save();
        }

        $supplyOrders = SupplyOrder::query()->withoutGlobalScopes()->get();
        foreach ($supplyOrders as $supplyOrder) {
            $supplyOrder->delete();
        }

        $prodOrders = ProdOrder::query()->withoutGlobalScopes()->get();
        foreach ($prodOrders as $prodOrder) {
            $prodOrder->delete();
        }

        $prodOrderGroups = ProdOrderGroup::query()->withoutGlobalScopes()->get();
        foreach ($prodOrderGroups as $prodOrderGroup) {
            $prodOrderGroup->delete();
        }

        $inventoryItems = InventoryItem::query()->withoutGlobalScopes()->get();
        foreach ($inventoryItems as $inventoryItem) {
            $inventoryItem->delete();
        }

        $inventoryTransactions = InventoryTransaction::query()->withoutGlobalScopes()->get();
        foreach ($inventoryTransactions as $inventoryTransaction) {
            $inventoryTransaction->delete();
        }

        $miniInventories = MiniInventory::query()->withoutGlobalScopes()->get();
        foreach ($miniInventories as $miniInventory) {
            $miniInventory->delete();
        }

        $tasks = Task::query()->withoutGlobalScopes()->get();
        foreach ($tasks as $task) {
            $task->delete();
        }

        $this->info('Products generated successfully.');
    }
}
