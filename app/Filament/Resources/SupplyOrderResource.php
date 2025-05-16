<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\RoleType;
use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Filament\Resources\SupplyOrderResource\Pages;
use App\Filament\Resources\SupplyOrderResource\RelationManagers;
use App\Models\SupplierProduct;
use App\Models\SupplyOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupplyOrderResource extends Resource
{
    protected static ?string $model = SupplyOrder::class;
    protected static ?int $navigationSort = 6;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::PLANNING_MANAGER,
            RoleType::PRODUCTION_MANAGER,
            RoleType::ALLOCATION_MANAGER,
            RoleType::SUPPLY_MANAGER,
            RoleType::SENIOR_SUPPLY_MANAGER,
            RoleType::STOCK_MANAGER,
            RoleType::SENIOR_STOCK_MANAGER,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereRelation(
            'warehouse', 'organization_id',
            auth()->user()->organization_id
        );
    }

    public static function canCreate(): bool
    {
        return in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::SUPPLY_MANAGER,
            RoleType::SENIOR_SUPPLY_MANAGER,
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        return in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::SUPPLY_MANAGER,
            RoleType::SENIOR_SUPPLY_MANAGER,
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('delivered_at')->default(null),
                Forms\Components\Hidden::make('delivered_by')->default(null),

                Forms\Components\Fieldset::make()->label('Details')->schema([
                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->native(false)
                            ->relationship('warehouse', 'name')
                            ->reactive()
                            ->required(),
                        Forms\Components\Select::make('product_category_id')
                            ->native(false)
                            ->searchable()
                            ->relationship('productCategory', 'name')
                            ->reactive()
                            ->preload()
                            ->required(),
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
                            ->required()
                            ->reactive(),
                        Forms\Components\Placeholder::make('total_price')
                            ->content(function ($record) {
                                return $record?->total_price ?? 0;
                            })
                    ]),

                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\Toggle::make('is_custom_status')
                            ->inline(false)
                            ->label('Is custom status')
                            ->reactive(),

                        Forms\Components\Select::make('state')
                            ->options([
                                SupplyOrderState::Created->value => SupplyOrderState::Created->getLabel(),
                                SupplyOrderState::InProgress->value => SupplyOrderState::InProgress->getLabel(),
                                SupplyOrderState::Delivered->value => SupplyOrderState::Delivered->getLabel(),
                            ])
                            ->reactive(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->visible(fn($get) => $get('is_custom_status') == false)
                            ->options(SupplyOrderStatus::class)
                            ->reactive(),

                        Forms\Components\TextInput::make('custom_status')
                            ->label('Custom Status')
                            ->visible(fn($get) => $get('is_custom_status') == true)
                            ->reactive(),

                        Forms\Components\Placeholder::make('prod_order_id')
                            ->content(function ($record) {
                                return $record?->prodOrder ? $record->prodOrder->number : '-';
                            })
                    ])->visible(fn($record, $get) => !$record?->closed_at),

                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\Placeholder::make('status')
                            ->label('Current status')
                            ->content(fn ($record) => $record?->getStatus()),
                        Forms\Components\Placeholder::make('prod_order_id')
                            ->content(function ($record) {
                                return $record?->prodOrder ? $record->prodOrder->number : '-';
                            })
                    ])->visible(fn($record, $get) => $record?->closed_at)
                ]),

                Forms\Components\Section::make('Steps history')
                    ->collapsed()
                    ->columnSpan(1)
                    ->schema([
                        Forms\Components\Repeater::make('steps')
                            ->label(false)
                            ->relationship('steps')
                            ->reactive()
                            ->schema([
                                Forms\Components\Placeholder::make('status')
                                    ->label(false)
                                    ->content(fn ($record) => $record?->getStatus()),
                            ])
                            ->disabled()
                    ]),

                Forms\Components\Section::make('Locations history')
                    ->collapsed()
                    ->columnSpan(1)
                    ->schema([
                        Forms\Components\Repeater::make('locations')
                            ->label(false)
                            ->relationship('locations')
                            ->reactive()
                            ->simple(
                                Forms\Components\TextInput::make('location')
                                    ->label('Location')
                                    ->required()
                            )
                            ->addActionLabel('Add location')
                    ])


            ])->disabled(fn($record) => $record?->closed_at);
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
                $query->with(['warehouse', 'productCategory', 'supplier', 'createdBy']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Order number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Current status')
                    ->badge()
                    ->getStateUsing(fn (SupplyOrder $record) => $record?->getStatus()),
                Tables\Columns\TextColumn::make('location')
                    ->label('Current location')
                    ->getStateUsing(function (SupplyOrder $record) {
                        $location = $record?->locations()->latest()->first();
                        return $location ? $location->location : '-';
                    }),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productCategory.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->numeric()
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
//            ->recordUrl(fn($record) => null)
            ->actions([
//                Tables\Actions\Action::make('complete')
//                    ->label('Complete')
//                    ->hidden(fn($record) => $record?->status == OrderStatus::Completed && self::canAction())
//                    ->requiresConfirmation()
//                    ->action(function (SupplyOrder $supplyOrder) {
//                        try {
//                            app(SupplyOrderService::class)->completeOrder($supplyOrder);
//                        } catch (\Throwable $e) {
//                            Notification::make()
//                                ->title('Error')
//                                ->body($e->getMessage())
//                                ->danger()
//                                ->send();
//                        }
//                    }),
                Tables\Actions\EditAction::make()
                    ->hidden(fn($record) => $record?->status == OrderStatus::Completed),
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
            RelationManagers\ProductsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplyOrders::route('/'),
            'create' => Pages\CreateSupplyOrder::route('/create'),
            'edit' => Pages\EditSupplyOrder::route('/{record}/edit'),
            'view' => Pages\ViewSupplyOrder::route('/{record}/view'),
        ];
    }
}
