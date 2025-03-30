<?php

namespace App\Filament\Resources;

use App\Enums\RoleType;
use App\Filament\Resources\InventoryItemResource\Pages;
use App\Filament\Resources\InventoryItemResource\RelationManagers;
use App\Models\Inventory;
use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;
    protected static ?string $navigationGroup = 'Warehouse';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                Forms\Components\Select::make('inventory_id')
                    ->options(function () {
                        $result = [];
                        foreach (Inventory::all() as $inventory) {
                            $result[$inventory->id] = $inventory->product->name . ' (' . $inventory->warehouse->name . ')';
                        }
                        return $result;
                    })
                    ->required(),
                Forms\Components\Select::make('storage_location_id')
                    ->relationship('storageLocation', 'name'),
                Forms\Components\TextInput::make('storage_floor')
                    ->numeric(),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function ($query) {
                $query->with(['inventory.warehouse', 'inventory.product', 'storageLocation']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('inventory.warehouse.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('inventory.product.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('storageLocation.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('storage_floor')
                    ->numeric()
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

//    public static function canEdit(Model $record): bool
//    {
//        return false;
//    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInventoryItems::route('/'),
        ];
    }
}
