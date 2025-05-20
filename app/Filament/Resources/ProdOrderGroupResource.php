<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Enums\RoleType;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\ProdOrderGroup;
use Filament\Resources\Resource;
use App\Enums\ProdOrderGroupType;
use Filament\Forms\Components\Grid;
use App\Filament\Resources\ProdOrderGroupResource\Pages;
use App\Filament\Resources\ProdOrderGroupResource\RelationManagers\ProdOrdersRelationManager;

class ProdOrderGroupResource extends Resource
{
    protected static ?string $model = ProdOrderGroup::class;
    protected static ?string $label = 'Prod order';
    protected static ?int $navigationSort = 2;

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
                Grid::make(3)->schema([

                    Forms\Components\Select::make('type')
                        ->options(ProdOrderGroupType::class)
                        ->reactive()
                        ->required(),

                    Forms\Components\Select::make('warehouse_id')
                        ->relationship('warehouse', 'name')
                        ->required(),

                    Forms\Components\Select::make('organization_id')
                        ->label('Agent')
                        ->relationship('organization', 'name')
                        ->visible(fn($get) => $get('type') == ProdOrderGroupType::ByOrder->value)
                        ->required(),

                    Forms\Components\DatePicker::make('deadline')
                        ->label('Срок выполнения')
                        ->visible(fn($get) => $get('type') == ProdOrderGroupType::ByCatalog->value)
                        ->required(),

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            ProdOrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdOrderGroups::route('/'),
            'create' => Pages\CreateProdOrderGroup::route('/create'),
            'edit' => Pages\EditProdOrderGroup::route('/{record}/edit'),
        ];
    }
}
