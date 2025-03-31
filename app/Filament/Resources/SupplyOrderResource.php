<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\RoleType;
use App\Filament\Resources\SupplyOrderResource\Pages;
use App\Filament\Resources\SupplyOrderResource\RelationManagers;
use App\Models\Product;
use App\Models\Supplier;
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
                    ->options(fn() => Supplier::pluck('name', 'id')->toArray()),
                Forms\Components\TextInput::make('total_price')
                    ->numeric(),
                Forms\Components\TextInput::make('unit_price')
                    ->numeric(),
//                Forms\Components\TextInput::make('created_by')
//                    ->required()
//                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['product', 'supplier', 'createdBy']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
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
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label('Complete')
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
                Tables\Actions\EditAction::make(),
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
