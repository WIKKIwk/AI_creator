<?php

namespace App\Filament\Resources\ProdOrderGroupResource\RelationManagers;

use Filament\Forms;
use App\Models\Product;
use Filament\Forms\Form;
use App\Enums\ProductType;
use App\Models\ProdOrderGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProdOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'prodOrders';

    public function form(Form $form): Form
    {
        /** @var ProdOrderGroup $prodOrderGroup */
        $prodOrderGroup = $this->getOwnerRecord();

        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('product_id')
                        ->relationship(
                            'product',
                            'name',
                            function($query) {
                                $query->where('type', ProductType::ReadyProduct);
                            }
                        )
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_id')
            ->modifyQueryUsing(function (Builder $query) {
                $query->with([
                    'product' => fn($query) => $query->with('category'),
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('product.name'),
                Tables\Columns\TextColumn::make('quantity')
                    ->formatStateUsing(function ($record) {
                        return $record->quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('offer_price'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
