<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use Illuminate\View\View;
use App\Filament\Resources\ProdOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProdOrders extends ListRecords
{
    protected static string $resource = ProdOrderResource::class;

    public bool $showCustomModal = false;

    public function openCustomModal(): void
    {
        $this->showCustomModal = true;
    }

    public function doSomethingThenOpenModal(): void
    {
        // Your logic here (e.g., validation or DB updates)
        $this->openCustomModal();
    }

    protected function getModal(): ?View
    {
        return $this->showCustomModal
            ? view('filament.resources.custom-modal')
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Open Custom Modal')
                ->action(fn () => $this->openCustomModal())
                ->label('Trigger Modal'),
            Actions\CreateAction::make(),
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.resources.prod-order-resource.pages.list-prod-orders', [
            'missingAssets' => [],
            'showMissingAssetsModal' => true,
        ]);
    }

}
