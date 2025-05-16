<?php

namespace App\Filament\Resources;

use App\Enums\RoleType;
use App\Filament\Resources\WorkStationCategoryResource\Pages;
use App\Filament\Resources\WorkStationCategoryResource\RelationManagers;
use App\Models\WorkStationCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WorkStationCategoryResource extends Resource
{
    protected static ?string $model = WorkStationCategory::class;
    protected static ?string $navigationGroup = 'Manage';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
                RoleType::ADMIN,
                RoleType::PRODUCTION_MANAGER,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(
            'organization_id',
            auth()->user()->organization_id
        );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('organization_id'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('short_code')
                    ->maxLength(255),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('short_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWorkStationCategories::route('/'),
        ];
    }
}
