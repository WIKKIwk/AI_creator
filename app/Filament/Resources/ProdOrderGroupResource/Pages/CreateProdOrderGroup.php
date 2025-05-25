<?php

namespace App\Filament\Resources\ProdOrderGroupResource\Pages;

use App\Events\ProdOrderChanged;
use Filament\Forms;
use App\Models\Product;
use Filament\Forms\Form;
use App\Enums\ProductType;
use App\Enums\OrderStatus;
use Illuminate\Support\Arr;
use App\Enums\ProdOrderGroupType;
use App\Services\ProdOrderService;
use Filament\Forms\Components\Grid;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\ProdOrderGroupResource;

class CreateProdOrderGroup extends CreateRecord
{
    protected static string $resource = ProdOrderGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->user()->id;

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function afterCreate(): void
    {
        ProdOrderChanged::dispatch($this->record);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    Forms\Components\Select::make('type')
                        ->options(ProdOrderGroupType::class)
                        ->reactive()
                        ->required(),

                    Forms\Components\Select::make('warehouse_id')
                        ->relationship('warehouse', 'name')
                        ->required(),

                    Forms\Components\Select::make('organization_id')
                        ->label('Agent')
                        ->relationship(
                            'organization',
                            'name',
                            fn($query) => $query->whereNot('id', auth()->user()->organization_id)
                        )
                        ->visible(fn($get) => $get('type') == ProdOrderGroupType::ByOrder->value)
                        ->required(),

                    Forms\Components\DatePicker::make('deadline')
                        ->label('Срок выполнения')
                        ->visible(fn($get) => $get('type') == ProdOrderGroupType::ByCatalog->value)
                        ->required(),

                    Forms\Components\Repeater::make('prodOrders')
                        ->label('Products')
                        ->relationship('prodOrders')
                        ->columnSpanFull()
                        ->schema([

                            Grid::make(3)->schema([
                                Forms\Components\Select::make('product_id')
                                    ->relationship(
                                        'product',
                                        'name',
                                        function($query, $get) {
                                            $selfProductIds = $get('product_id');
                                            $products = Arr::get($get('../../'), 'prodOrders', []);
                                            $existProductIds = [];
                                            foreach ($products as $product) {
                                                if (!$selfProductIds && $product['product_id'] ?? null) {
                                                    $existProductIds[] = $product['product_id'];
                                                }
                                            }

                                            $query
                                                ->where('type', ProductType::ReadyProduct)
                                                ->whereNotIn('id', $existProductIds);
                                        }
                                    )
                                    ->getOptionLabelFromRecordUsing(function($record) {
                                        /** @var Product $record */
                                        return $record->ready_product_id ? $record->name : $record->catName;
                                    })
                                    ->required()
                                    ->reactive(),

                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->suffix(function($get) {
                                        /** @var Product|null $product */
                                        $product = $get('product_id') ? Product::query()->find(
                                            $get('product_id')
                                        ) : null;
                                        return $product?->category?->measure_unit?->getLabel();
                                    })
                                    ->numeric(),

                                Forms\Components\TextInput::make('offer_price')
                                    ->required()
                                    ->numeric(),
                            ])

                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(function(array $data, $get): array {
                            $data['status'] = OrderStatus::Pending;

                            $data['total_cost'] = app(ProdOrderService::class)->calculateTotalCost(
                                $data['product_id'],
                                $get('warehouse_id')
                            );
                            $data['deadline'] = app(ProdOrderService::class)->calculateDeadline(
                                $data['product_id']
                            );
                            return $data;
                        })
                ]),
            ]);
    }
}
