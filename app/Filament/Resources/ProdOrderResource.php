<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\ProdOrderResource\Pages;
use App\Filament\Resources\ProdOrderResource\RelationManagers;
use App\Models\ProdOrder;
use App\Services\ProdOrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProdOrderResource extends Resource
{
    protected static ?string $model = ProdOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->native(false)
                    ->searchable()
                    ->relationship('warehouse', 'name')
                    ->required(),
                Forms\Components\Select::make('product_id')
                    ->native(false)
                    ->searchable()
                    ->relationship('product', 'name')
                    ->required(),
                Forms\Components\Select::make('agent_id')
                    ->native(false)
                    ->relationship('agent', 'name')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('offer_price')
                    ->required()
                    ->numeric(),

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
                        ->disabled()
                        ->required(),
                    Forms\Components\TextInput::make('deadline')
                        ->label('Expected deadline')
                        ->hidden(fn($record) => !$record?->id)
                        ->disabled()
                        ->required(),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['product', 'agent', 'warehouse']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('agent.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('offer_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
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
                Tables\Actions\Action::make('startProduction')
                    ->label('Start')
                    ->hidden(fn($record) => $record->status != OrderStatus::Pending)
                    ->action(function ($record) {
                        try {
                            app(ProdOrderService::class)->start($record);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
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
