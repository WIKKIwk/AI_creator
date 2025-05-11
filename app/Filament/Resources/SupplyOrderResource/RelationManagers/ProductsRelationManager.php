<?php

namespace App\Filament\Resources\SupplyOrderResource\RelationManagers;

use App\Models\Product;
use App\Models\SupplyOrder;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('product_id')
                        ->native(false)
                        ->relationship(
                            'product',
                            'name',
                            function ($query) {
                                $query->where('product_category_id', $this->getOwnerRecord()->product_category_id);
                            }
                        )
                        ->rules([
                            fn(Forms\Get $get): Closure => function (string $attribute, $value, $fail) use ($get) {
                                /** @var SupplyOrder $supplyOrder */
                                $supplyOrder = $this->getOwnerRecord();
                                if (
                                    $supplyOrder->products()
                                        ->when(
                                            $get('id'),
                                            fn($query) => $query->whereNot('id', $get('id'))
                                        )
                                        ->where('supply_order_id', $supplyOrder->id)
                                        ->where('product_id', $get('product_id'))
                                        ->exists()
                                ) {
                                    $fail('This product is already added to the order.');
                                }
                            }
                        ])
                        ->reactive()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('expected_quantity')
                        ->suffix(function ($get) {
                            /** @var Product|null $product */
                            $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                            if ($product?->category?->measure_unit) {
                                return $product->category->measure_unit->getLabel();
                            }
                            return null;
                        })
                        ->required()
                        ->numeric(),

                    Forms\Components\TextInput::make('actual_quantity')
                        ->visible(fn($record) => $record?->id)
                        ->suffix(function ($get) {
                            /** @var Product|null $product */
                            $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                            if ($product?->category?->measure_unit) {
                                return $product->category->measure_unit->getLabel();
                            }
                            return null;
                        })
                        ->numeric(),
                ]),

            ]);
    }

    protected function canCreate(): bool
    {
        return !$this->getOwnerRecord()->closed_at || !$this->getOwnerRecord()->delivered_at;
    }

    protected function canEdit(Model $record): bool
    {
        return !$this->getOwnerRecord()->closed_at || !$this->getOwnerRecord()->delivered_at;
    }

    protected function canDelete(Model $record): bool
    {
        return !$this->getOwnerRecord()->closed_at || !$this->getOwnerRecord()->delivered_at;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordClasses(function ($record) {
                if ($record->actual_quantity > 0 && $record->expected_quantity != $record->actual_quantity) {
                    return 'row-warning';
                }

                return null;
            })
            ->recordTitleAttribute('product_id')
            ->modifyQueryUsing(fn($query) => $query->with('product'))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->width('500px'),
                Tables\Columns\TextColumn::make('expected_quantity')
                    ->formatStateUsing(function ($record) {
                        return $record->expected_quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    }),
                Tables\Columns\TextColumn::make('actual_quantity')
                    ->formatStateUsing(function ($record) {
                        return ($record->actual_quantity ?? 0) . ' ' . $record->product->category?->measure_unit?->getLabel();
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Product'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }
}
