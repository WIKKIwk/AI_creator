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
            Actions\Action::make('Open Custom Modal')
                ->action(fn() => $this->confirmAction())
                ->label('Trigger Modal'),

            Actions\CreateAction::make(),
        ];
    }

    public function confirmAction(): void
    {
        // Logic to handle the confirmation action
        $this->dispatch('openModal', [
            'modal' => [
                'heading' => 'Confirm Deletion',
                'content' => 'Are you sure you want to delete this item?',
                'confirmCallback' => 'deleteConfirmed',
                'cancelCallback' => 'resetState',
            ],
        ]);
    }
}
