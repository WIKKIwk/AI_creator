<?php

namespace App\Filament\Resources\SupplyOrderResource\Pages;

use App\Filament\Resources\SupplyOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplyOrders extends ListRecords
{
    protected static string $resource = SupplyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
