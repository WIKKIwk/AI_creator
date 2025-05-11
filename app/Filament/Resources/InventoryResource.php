<?php

namespace App\Filament\Resources;

use App\Enums\RoleType;
use App\Filament\Resources\InventoryResource\Actions\InventoryItemsAction;
use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Resources\InventoryResource\RelationManagers;
use App\Models\Inventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;
    protected static ?string $navigationGroup = 'Warehouse';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function isWarehouseWorker(): bool
    {
        return auth()->user()->role == RoleType::STOCK_MANAGER;
    }

    public static function canAccess(): bool
    {
        if (self::isWarehouseWorker() && !auth()->user()->warehouse_id) {
            return false;
        }

        return in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::PLANNING_MANAGER,
            RoleType::PRODUCTION_MANAGER,
            RoleType::ALLOCATION_MANAGER,
            RoleType::SENIOR_STOCK_MANAGER,
            RoleType::STOCK_MANAGER,
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->required(),
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('unit_cost')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                $query
                    ->with('items')
                    ->when(
                        in_array(auth()->user()->role, [
                            RoleType::SENIOR_STOCK_MANAGER,
                            RoleType::STOCK_MANAGER,
                        ]),
                        fn($query) => $query->where('warehouse_id', auth()->user()->warehouse_id)
                    );
            })
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->hidden(self::isWarehouseWorker())
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->formatStateUsing(function ($record) {
                        return $record->quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_cost')
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
            ])
            ->filters([
                //
            ])
            ->recordUrl(null)
            ->recordAction(InventoryItemsAction::class)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                InventoryItemsAction::make()
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->label('')
                    ->icon(''),
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

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInventories::route('/'),
        ];
    }
}
