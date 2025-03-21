<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdOrderResource\Pages;
use App\Filament\Resources\ProdOrderResource\RelationManagers;
use App\Models\ProdOrder;
use Filament\Forms;
use Filament\Forms\Form;
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
                Forms\Components\TextInput::make('total_cost')
                    ->numeric(),
                Forms\Components\TextInput::make('deadline')
                    ->numeric(),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['product', 'agent']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->numeric()
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
                Tables\Columns\TextColumn::make('total_cost')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deadline')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
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
            ->actions([
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
        ];
    }
}
