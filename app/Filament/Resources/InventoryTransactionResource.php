<?php

namespace App\Filament\Resources;

use App\Enums\TransactionType;
use App\Filament\Resources\InventoryTransactionResource\Pages;
use App\Filament\Resources\InventoryTransactionResource\RelationManagers;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;
    protected static ?string $navigationGroup = 'Warehouse';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->native(false)
                    ->relationship('product', 'name')
                    ->reactive()
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->suffix(function ($get) {
                        /** @var Product|null $product */
                        $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                        if ($product?->measure_unit) {
                            return $product->measure_unit->getLabel();
                        }
                        return null;
                    })
                    ->numeric(),
                Forms\Components\TextInput::make('cost')
                    ->label('Cost')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('storage_location_id')
                    ->relationship('storageLocation', 'name'),
                Forms\Components\Select::make('type')
                    ->native(false)
                    ->options(TransactionType::class)
                    ->required(),
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cost')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('storageLocation.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInventoryTransactions::route('/'),
        ];
    }
}
