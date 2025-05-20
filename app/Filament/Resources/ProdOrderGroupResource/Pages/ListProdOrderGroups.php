<?php

namespace App\Filament\Resources\ProdOrderGroupResource\Pages;

use App\Filament\Resources\ProdOrderGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProdOrderGroups extends ListRecords
{
    protected static string $resource = ProdOrderGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
