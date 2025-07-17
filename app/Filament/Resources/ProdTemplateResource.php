<?php

namespace App\Filament\Resources;

use App\Enums\ProductType;
use App\Enums\RoleType;
use App\Filament\Resources\ProdTemplateResource\Pages;
use App\Filament\Resources\ProdTemplateResource\RelationManagers;
use App\Models\ProdTemplate\ProdTemplate;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProdTemplateResource extends Resource
{
    protected static ?string $model = ProdTemplate::class;
    protected static ?string $label = 'Shablon';
    protected static ?string $pluralLabel = 'Shablonlar';
    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::PLANNING_MANAGER,
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('organization_id'),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Name'),

                    Forms\Components\Select::make('product_id')
                        ->label('Ready product')
                        ->native(false)
                        ->relationship(
                            'product',
                            'name',
                            fn ($query) => $query->where('type', ProductType::ReadyProduct)
                        )
                        ->getOptionLabelFromRecordUsing(function($record) {
                            /** @var Product $record */
                            return $record->ready_product_id ? $record->name : $record->catName;
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Textarea::make('comment')
                        ->label('Comment'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.catName')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime()
                    ->sortable(),
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
            RelationManagers\StepsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdTemplates::route('/'),
            'create' => Pages\CreateProdTemplate::route('/create'),
            'edit' => Pages\EditProdTemplate::route('/{record}/edit'),
        ];
    }
}
