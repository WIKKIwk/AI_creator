<?php

namespace App\Filament\Resources\SupplyOrderResource\Pages;

use Filament\Forms;
use App\Enums\OrderStatus;
use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Filament\Resources\SupplyOrderResource;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplyOrder extends CreateRecord
{
    protected static string $resource = SupplyOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['state'] = SupplyOrderState::Created;
        $data['created_by'] = auth()->id();

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function afterCreate(): void
    {
        $this->record->steps()->create([
            'state' => $this->record->state,
            'status' => $this->record->status,
            'created_by' => auth()->user()->id,
            'created_at' => now(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\Select::make('warehouse_id')
                        ->native(false)
                        ->relationship('warehouse', 'name')
                        ->reactive()
                        ->required(),
                    Forms\Components\Select::make('product_category_id')
                        ->native(false)
                        ->searchable()
                        ->relationship('productCategory', 'name')
                        ->reactive()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('supplier_organization_id')
                        ->native(false)
                        ->relationship('supplierOrganization', 'name')
                        ->reactive(),
                ]),
            ]);
    }
}
