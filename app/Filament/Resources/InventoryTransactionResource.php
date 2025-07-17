<?php

namespace App\Filament\Resources;

use App\Enums\RoleType;
use App\Enums\TransactionType;
use App\Filament\Resources\InventoryTransactionResource\Pages;
use App\Filament\Resources\InventoryTransactionResource\RelationManagers;
use App\Models\Inventory\InventoryTransaction;
use App\Models\Product;
use App\Services\UserService;
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
    protected static ?string $label = 'Ombor Kirim/Chiqim';
    protected static ?string $pluralLabel = 'Ombor Kirim/Chiqim';
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

        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
                RoleType::ADMIN,
                RoleType::STOCK_MANAGER,
                RoleType::SENIOR_STOCK_MANAGER,
            ]);
    }

    public static function form(Form $form): Form
    {
        $schema = [
            Forms\Components\Select::make('product_id')
                ->native(false)
                ->relationship('product', 'name')
                ->getOptionLabelFromRecordUsing(function($record) {
                    /** @var Product $record */
                    return $record->ready_product_id ? $record->name : $record->catName;
                })
                ->reactive()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('quantity')
                ->required()
                ->suffix(function ($get) {
                    /** @var Product|null $product */
                    $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                    return $product?->getMeasureUnit()->getLabel();
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
                ->label('Warehouse')
                ->relationship('warehouse', 'name')
                ->visible(!self::isWarehouseWorker())
                ->required()
        ];

        if (self::isWarehouseWorker()) {
            $schema = array_merge($schema, [
                Forms\Components\Hidden::make('warehouse_id')
                    ->visible(self::isWarehouseWorker())
                    ->formatStateUsing(fn () => auth()->user()->warehouse_id),
                Forms\Components\TextInput::make('warehouse_label')
                    ->label('Warehouse')
                    ->visible(self::isWarehouseWorker())
                    ->formatStateUsing(fn () => auth()->user()->warehouse?->name)
                    ->readOnly()
                    ->required(),
            ]);
        }

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query
                    ->with([
                        'warehouse',
                        'product' => fn($query) => $query->with('category'),
                        'storageLocation',
                        'workStation',
                    ])
                    ->when(
                        in_array(auth()->user()->role, [
                            RoleType::SENIOR_STOCK_MANAGER,
                            RoleType::STOCK_MANAGER,
                        ]),
                        fn($query) => $query->where('warehouse_id', auth()->user()->warehouse_id)
                    );
            })
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->hidden(self::isWarehouseWorker())
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.catName')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->formatStateUsing(function ($record) {
                        return $record->quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    })
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
//                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return UserService::isSuperAdmin();
    }

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
