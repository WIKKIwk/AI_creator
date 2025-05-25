<?php

namespace App\Filament\Resources\InventoryTransactionResource\Pages;

use App\Enums\TransactionType;
use App\Filament\Resources\InventoryTransactionResource;
use App\Services\InventoryService;
use App\Services\TransactionService;
use Exception;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageInventoryTransactions extends ManageRecords
{
    protected static string $resource = InventoryTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->before(function ($data) {
                    /** @var TransactionService $transactionService */
                    /** @var InventoryService $inventoryService */
                    $transactionService = app(TransactionService::class);
                    $inventoryService = app(InventoryService::class);

                    $type = $data['type'];
                    if ($type == TransactionType::In->value) {
                        $transactionService->addStock(
                            $data['product_id'],
                            $data['quantity'],
                            $data['warehouse_id'],
                            $data['cost'] ?? null,
                            $data['storage_location_id'] ?? null,
                            withTransaction: false
                        );
                    } elseif ($type == TransactionType::Out->value) {
                        $inventory = $inventoryService->getInventory($data['product_id'], $data['warehouse_id']);
                        $inventoryItem = $inventoryService->getInventoryItem(
                            $inventory,
                            $data['storage_location_id'] ?? null
                        );

                        if (!$inventoryItem || $inventoryItem->quantity < $data['quantity']) {
                            throw new Exception('Insufficient stock');
                        }

                        $inventoryItem->quantity -= $data['quantity'];
                        $inventoryItem->save();
                    }
                })
        ];
    }
}
