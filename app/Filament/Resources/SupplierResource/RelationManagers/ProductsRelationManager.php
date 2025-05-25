<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Enums\Currency;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('product_id')
                        ->relationship('product', 'name')
                        ->getOptionLabelFromRecordUsing(function($record) {
                            /** @var Product $record */
                            return $record->ready_product_id ? $record->name : $record->catName;
                        })
                        ->required(),
                    Forms\Components\TextInput::make('unit_price')
                        ->required()
                        ->numeric(),
                    Forms\Components\Select::make('currency')
                        ->options(Currency::class)
                        ->required(),
                ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['product']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('product.catName')
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->formatStateUsing(fn ($record) => $record->unit_price . ' ' . $record->currency?->getLabel())
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Assign Product'),
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
