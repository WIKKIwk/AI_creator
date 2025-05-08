<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use Illuminate\View\View;
use App\Filament\Resources\ProdOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProdOrders extends ListRecords
{
    protected static string $resource = ProdOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Open Custom Modal')
                ->action(fn () => $this->dispatch('open-modal', id:'custom-modal'))
                ->label('Trigger Modal'),
            Actions\CreateAction::make(),
        ];
    }
}
