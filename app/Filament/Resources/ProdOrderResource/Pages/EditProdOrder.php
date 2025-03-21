<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Filament\Resources\ProdOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProdOrder extends EditRecord
{
    protected static string $resource = ProdOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
