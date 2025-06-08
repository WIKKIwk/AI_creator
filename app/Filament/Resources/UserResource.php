<?php

namespace App\Filament\Resources;

use App\Enums\RoleType;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Services\UserService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'Manage';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(!UserService::isSuperAdmin(), function (Builder $query) {
                $query->where('organization_id', auth()->user()->organization_id);
            })
            ->whereNot('id', auth()->user()->id);
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [
            RoleType::ADMIN,
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([

                    Forms\Components\Select::make('organization_id')
                        ->visible(UserService::isSuperAdmin())
                        ->relationship('organization', 'name')
                        ->required(),

                    Forms\Components\Hidden::make('organization_id')->visible(!UserService::isSuperAdmin()),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                ]),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->required(fn($record) => $record === null)
                        ->maxLength(255),
                    Forms\Components\Select::make('role')
                        ->options(RoleType::class)
                        ->required(),
                    Forms\Components\TextInput::make('chat_id')
                        ->rules(fn ($get, $record) => [
                            Rule::unique('users', 'chat_id')->ignore($record?->id),
                        ]),
                ]),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('warehouse_id')
                        ->relationship('warehouse', 'name'),
                    Forms\Components\Select::make('work_station_id')
                        ->relationship('workStation', 'name'),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with([
                    'organization',
                    'warehouse',
                    'workStation',
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->visible(UserService::isSuperAdmin())
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('auth_code'),
                Tables\Columns\TextColumn::make('chat_id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('workStation.name')
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
            ->recordUrl(null)
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
