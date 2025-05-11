<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\RoleType;
use App\Filament\Resources\ProdOrderResource\Pages;
use App\Filament\Resources\ProdOrderResource\RelationManagers;
use App\Models\ProdOrder;
use App\Models\Product;
use App\Services\ProdOrderService;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class ProdOrderResource extends Resource
{
    protected static ?string $model = ProdOrder::class;
    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::PLANNING_MANAGER,
            RoleType::PRODUCTION_MANAGER,
            RoleType::ALLOCATION_MANAGER,
            RoleType::SENIOR_STOCK_MANAGER,
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('warehouse_id')
                        ->relationship('warehouse', 'name')
                        ->required(),

                    Forms\Components\Select::make('product_id')
                        ->relationship(
                            'product',
                            'name',
                            fn($query) => $query->where('type', ProductType::ReadyProduct)
                        )
                        ->reactive()
                        ->required(),

                    Forms\Components\Select::make('agent_id')
                        ->relationship('agent', 'name')
                        ->required(),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('quantity')
                        ->required()
                        ->suffix(function ($get) {
                            /** @var Product|null $product */
                            $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                            return $product?->category?->measure_unit?->getLabel();
                        })
                        ->numeric(),

                    Forms\Components\TextInput::make('offer_price')
                        ->required()
                        ->numeric(),
                ]),

                Forms\Components\Grid::make(3)->schema([

                    Forms\Components\TextInput::make('status')
                        ->default(OrderStatus::Pending->value)
                        ->formatStateUsing(fn($state) => OrderStatus::tryFrom($state)?->getLabel())
                        ->hidden(fn($record) => !$record?->id)
                        ->disabled()
                        ->required(),

                    Forms\Components\TextInput::make('total_cost')
                        ->label('Expected total Cost')
                        ->hidden(fn($record) => !$record?->id)
                        ->disabled(),

                    Forms\Components\TextInput::make('deadline')
                        ->label('Expected deadline')
                        ->hidden(fn($record) => !$record?->id)
                        ->disabled(),
                ])
            ])->disabled(fn($record) => $record?->status == OrderStatus::Approved);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['product', 'agent', 'warehouse']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('number')
                    ->label('Order number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('agent.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->formatStateUsing(function ($record) {
                        return $record->quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('offer_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('confirmed_at')
                    ->getStateUsing(function ($record) {
                        if ($record->confirmed_at) {
                            return '<span class="text-green-500">✔️</span>';
                        }
                        return '<span class="text-red-500">❌</span>';
                    })
                    ->html()
                    ->sortable(),
                /*Tables\Columns\TextColumn::make('total_cost')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deadline')
                    ->numeric()
                    ->sortable(),*/
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

                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->visible(fn($record) => !$record->confirmed_at && in_array(auth()->user()->role, [
                        RoleType::ADMIN,
                        RoleType::PLANNING_MANAGER,
                        RoleType::PRODUCTION_MANAGER,
                    ]))
                    ->action(function (ProdOrder $record) {
                        try {
                            $record->confirm();
                            showSuccess('Order confirmed successfully');
                        } catch (Throwable $e) {
                            showError($e->getMessage());
                        }
                    })
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('startProduction')
                    ->label('Start')
                    ->visible(fn($record) => $record->confirmed_at && !$record->started_at)
                    ->action(function ($record, $livewire) {
                        try {
                            $insufficientAssets = app(ProdOrderService::class)->checkStart($record);
                            if (!empty($insufficientAssets)) {
                                $livewire->dispatch(
                                    'openModal',
                                    $record,
                                    $insufficientAssets,
                                    'startProdOrder'
                                );
                            }
                        } catch (Exception $e) {
                            showError($e->getMessage());
                        }
                    })
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('details')
                    ->label('Details')
                    ->hidden(fn($record) => $record->status == OrderStatus::Pending)
                    ->url(fn($record) => ProdOrderResource::getUrl('details', ['record' => $record]))
                    ->requiresConfirmation(),

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
            'index' => Pages\ListProdOrders::route('/'),
            'create' => Pages\CreateProdOrder::route('/create'),
            'edit' => Pages\EditProdOrder::route('/{record}/edit'),
            'details' => Pages\ProdOrderDetails::route('/{record}'),
        ];
    }
}
