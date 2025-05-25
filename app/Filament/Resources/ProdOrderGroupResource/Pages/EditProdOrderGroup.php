<?php

namespace App\Filament\Resources\ProdOrderGroupResource\Pages;

use App\Filament\Resources\ProdOrderGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProdOrderGroup extends EditRecord
{
    protected static string $resource = ProdOrderGroupResource::class;
    protected $listeners = ['refresh-page' => '$refresh'];
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
