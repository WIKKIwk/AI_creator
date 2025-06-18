<?php

namespace App\Filament\Resources;

use App\Enums\PartnerType;
use App\Enums\RoleType;
use App\Filament\Resources\OrganizationPartnerResource\Pages;
use App\Filament\Resources\OrganizationPartnerResource\RelationManagers;
use App\Models\OrganizationPartner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrganizationPartnerResource extends Resource
{
    protected static ?string $model = OrganizationPartner::class;
    protected static ?string $pluralLabel = 'Partners';
    protected static ?string $navigationGroup = 'Manage';
    protected static ?int $navigationSort = 6;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
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
                    Forms\Components\Hidden::make('organization_id')->default(auth()->user()->organization_id),

                    Forms\Components\Select::make('partner_id')
                        ->relationship(
                            'partner',
                            'name',
                            fn($query) => $query->whereNot('id', auth()->user()->organization_id)
                        )
                        ->required(),

                    Forms\Components\Select::make('type')
                        ->options(PartnerType::class)
                        ->required(),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
//                Tables\Columns\TextColumn::make('organization.name')
//                    ->numeric()
//                    ->sortable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
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
            RelationManagers\ProductsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizationPartners::route('/'),
            'create' => Pages\CreateOrganizationPartner::route('/create'),
            'edit' => Pages\EditOrganizationPartner::route('/{record}/edit'),
        ];
    }
}
