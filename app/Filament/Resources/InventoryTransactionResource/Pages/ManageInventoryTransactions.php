<?php

namespace App\Filament\Resources\InventoryTransactionResource\Pages;

use App\Filament\Resources\InventoryTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageInventoryTransactions extends ManageRecords
{
    protected static string $resource = InventoryTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
