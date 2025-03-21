<?php

namespace App\Filament\Resources\ProdTemplateResource\Pages;

use App\Filament\Resources\ProdTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProdTemplates extends ListRecords
{
    protected static string $resource = ProdTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
