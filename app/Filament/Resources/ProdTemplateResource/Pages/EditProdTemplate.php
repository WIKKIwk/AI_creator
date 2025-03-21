<?php

namespace App\Filament\Resources\ProdTemplateResource\Pages;

use App\Filament\Resources\ProdTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProdTemplate extends EditRecord
{
    protected static string $resource = ProdTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
