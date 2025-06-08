<?php

namespace App\Filament\Resources\SupplyOrderResource\Pages;

use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Filament\Resources\SupplyOrderResource;
use App\Models\SupplyOrder\SupplyOrder;
use App\Services\SupplyOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewSupplyOrder extends ViewRecord
{
    protected static string $resource = SupplyOrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Grid::make(3)->schema([

                TextEntry::make('warehouse_id')
                    ->label('Warehouse')
                    ->formatStateUsing(fn(SupplyOrder $record) => $record->warehouse->name),

                TextEntry::make('product_category_id')
                    ->label('Product Category')
                    ->formatStateUsing(fn(SupplyOrder $record) => $record->productCategory->name),

                TextEntry::make('supplier_id')
                    ->label('Supplier')
                    ->formatStateUsing(fn(SupplyOrder $record) => $record->supplier->name),

                TextEntry::make('total_price')
                    ->getStateUsing(fn(SupplyOrder $record) => $record->total_price ?? 0),

                TextEntry::make('status')
                    ->label('Current Status')
                    ->badge()
                    ->getStateUsing(fn(SupplyOrder $record) => $record->getStatus()),

                TextEntry::make('location')
                    ->label('Current Location')
                    ->getStateUsing(function (SupplyOrder $record) {
                        $lastLocation = $record->locations()->latest()->first();
                        if ($lastLocation) {
                            return $lastLocation->location;
                        }
                        return 'No location found';
                    }),
            ]),
        ]);
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('compare')
                ->visible(fn(SupplyOrder $record) => $record->hasStatus(
                    SupplyOrderState::Delivered,
                    SupplyOrderStatus::AwaitingWarehouseApproval->value
                ))
                ->label('Compare products')
                ->form([
                    Repeater::make('products')
                        ->deletable(false)
                        ->addable(false)
                        ->reorderable(false)
                        ->schema([
                            Placeholder::make('product_name')
                                ->label('Product')
                                ->content(fn($get) => $get('product_name')),
                            TextInput::make('actual_quantity')
                                ->label('Actual Quantity'),
                        ])
                        ->default(
                            fn() => $this->record->products->map(fn($item) => [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'product_name' => $item->product->name,
                                'actual_quantity' => 0,
                            ])->toArray()
                        )
                        ->columns(3)
                ])
                ->action(function (array $data, $livewire) {
                    try {
                        /** @var SupplyOrderService $supplyOrderService */
                        $supplyOrderService = app(SupplyOrderService::class);
                        $supplyOrderService->compareProducts($this->record, $data['products']);

                        showSuccess('Supply order sent for Supply Department approval.');
                        $livewire->dispatch('$refresh');
                    } catch (Throwable $e) {
                        showError($e->getMessage());
                    }
                })
        ];
    }
}
