<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ProdOrderResource;

class ListProdOrders extends ListRecords
{
    protected static string $resource = ProdOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
