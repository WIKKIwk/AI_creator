<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\RoleType;
use App\Filament\Resources\SupplyOrderResource\Pages;
use App\Filament\Resources\SupplyOrderResource\RelationManagers;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplyOrder;
use App\Services\SupplyOrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplyOrderResource extends Resource
{
    protected static ?string $model = SupplyOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAction(): bool
    {
        return in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::PLANNING_MANAGER,
        ]);
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::PLANNING_MANAGER,
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->native(false)
                    ->relationship('warehouse', 'name')
                    ->reactive()
                    ->required(),
                Forms\Components\Select::make('product_id')
                    ->native(false)
                    ->searchable()
                    ->relationship('product', 'name')
                    ->reactive()
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->suffix(function ($get) {
                        /** @var Product|null $product */
                        $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                        if ($product?->measure_unit) {
                            return $product->measure_unit->getLabel();
                        }
                        return null;
                    })
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('supplier_id')
                    ->native(false)
                    ->relationship('supplier', 'name')
                    ->afterStateUpdated(function ($get, $set) {
                        $supplierProduct = self::getSupplierProduct($get('supplier_id'), $get('product_id'));
                        if ($supplierProduct) {
                            $set('unit_price', $supplierProduct->unit_price);
                            $set('total_price', $supplierProduct->unit_price * $get('quantity'));
                        } else {
                            $set('unit_price', null);
                            $set('total_price', null);
                        }
                    })
                    ->reactive(),

                Forms\Components\TextInput::make('total_price')
                    ->prefix(function ($get) {
                        $supplierProduct = self::getSupplierProduct($get('supplier_id'), $get('product_id'));
                        return $supplierProduct?->currency->getLabel();
                    })
                    ->readOnly()
                    ->numeric(),

                Forms\Components\TextInput::make('unit_price')
                    ->readOnly()
                    ->numeric(),
//                Forms\Components\TextInput::make('created_by')
//                    ->required()
//                    ->numeric(),
            ]);
    }

    protected static function getSupplierProduct($supplierId, $productId): ?SupplierProduct
    {
        /** @var SupplierProduct $supplierProduct */
        $supplierProduct = SupplierProduct::query()
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->first();

        return $supplierProduct;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['warehouse', 'product', 'supplier', 'createdBy']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->numeric()
//                    ->formatStateUsing(fn($record, $state) => $state . ' ' . $record->currency->getLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->numeric()
//                    ->formatStateUsing(fn($record, $state) => $state . ' ' . $record->currency->getLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->recordUrl(fn($record) => null)
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->hidden(fn($record) => $record->status == OrderStatus::Completed && self::canAction())
                    ->requiresConfirmation()
                    ->action(function (SupplyOrder $supplyOrder) {
                        try {
                            app(SupplyOrderService::class)->completeOrder($supplyOrder);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make()
                    ->hidden(fn($record) => $record->status == OrderStatus::Completed),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplyOrders::route('/'),
            'create' => Pages\CreateSupplyOrder::route('/create'),
            'edit' => Pages\EditSupplyOrder::route('/{record}/edit'),
        ];
    }
}
