<?php

namespace App\Filament\Resources;

use App\Enums\RoleType;
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
use Illuminate\Database\Eloquent\Model;

class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;
    protected static ?string $navigationGroup = 'Warehouse';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function isWarehouseWorker(): bool
    {
        return auth()->user()->role == RoleType::STOCK_MANAGER;
    }

    public static function canAccess(): bool
    {
        if (auth()->user()->role == RoleType::STOCK_MANAGER) {
            if (!auth()->user()->warehouse_id) {
                return false;
            }
        }

        return in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::STOCK_MANAGER,
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->native(false)
                    ->relationship('product', 'name')
                    ->reactive()
                    ->preload()
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
                    ->visible(!self::isWarehouseWorker())
                    ->required(),
                Forms\Components\TextInput::make('warehouse_id')
                    ->visible(self::isWarehouseWorker())
                    ->readOnly()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query
                    ->with(['product', 'storageLocation', 'workStation', 'warehouse'])
                    ->when(auth()->user()->role == RoleType::STOCK_MANAGER, function ($query) {
                        $query->where('warehouse_id', auth()->user()->warehouse_id);
                    });
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->hidden(self::isWarehouseWorker())
                    ->numeric()
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('workStation.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function canEdit(Model $record): bool
    {
        return false;
    }

//    public static function canCreate(): bool
//    {
//        return false;
//    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInventoryTransactions::route('/'),
        ];
    }
}
