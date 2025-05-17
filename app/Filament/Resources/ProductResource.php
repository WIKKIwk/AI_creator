<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Enums\RoleType;
use App\Models\Product;
use Filament\Forms\Form;
use App\Enums\ProductType;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?int $navigationSort = 5;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
                RoleType::ADMIN,
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
//                Forms\Components\Hidden::make('organization_id'),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('type')
                        ->options(ProductType::class)
                        ->required(),
                    Forms\Components\Select::make('product_category_id')
                        ->relationship('category', 'name')
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Short code')
                        ->required(),
                    Forms\Components\TextInput::make('price')
                        ->numeric(),
                    Forms\Components\TextInput::make('description')
                        ->maxLength(255),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function(Builder $query) {
                $query->with(['category']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Short code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('measure_unit')
                    ->getStateUsing(fn($record) => $record->category->measure_unit)
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
