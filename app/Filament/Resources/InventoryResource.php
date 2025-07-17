<?php

namespace App\Filament\Resources;

use App\Enums\RoleType;
use App\Filament\Resources\InventoryResource\Actions\InventoryItemsAction;
use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Resources\InventoryResource\RelationManagers;
use App\Models\Inventory\Inventory;
use App\Models\Product;
use App\Services\UserService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;
    protected static ?string $navigationGroup = 'Warehouse';
    protected static ?string $label = 'Ombor';
    protected static ?string $pluralLabel = 'Ombor';

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

        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::PRODUCTION_MANAGER,
            RoleType::SENIOR_STOCK_MANAGER,
            RoleType::STOCK_MANAGER,
            RoleType::SENIOR_SUPPLY_MANAGER,
            RoleType::SUPPLY_MANAGER,
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
                    ->getOptionLabelFromRecordUsing(function($record) {
                        /** @var Product $record */
                        return $record->ready_product_id ? $record->name : $record->catName;
                    })
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
            ->searchable()
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                $query
                    ->with(['items', 'product' => fn($query) => $query->with('category')])
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
                Tables\Columns\TextColumn::make('product.catName')
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

    public static function canDeleteAny(): bool
    {
        return UserService::isSuperAdmin();
    }

    public static function canCreate(): bool
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
