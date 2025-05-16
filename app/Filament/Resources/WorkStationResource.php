<?php

namespace App\Filament\Resources;

use App\Enums\MeasureUnit;
use App\Enums\DurationUnit;
use App\Enums\RoleType;
use App\Filament\Resources\WorkStationResource\Pages;
use App\Filament\Resources\WorkStationResource\RelationManagers;
use App\Models\ProdOrder;
use App\Models\ProductCategory;
use App\Models\WorkStation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class WorkStationResource extends Resource
{
    protected static ?string $model = WorkStation::class;
    protected static ?string $navigationGroup = 'Manage';
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
                RoleType::ADMIN,
                RoleType::PRODUCTION_MANAGER,
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(4)->schema([

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Hidden::make('organization_id'),

                    Forms\Components\Select::make('work_station_category_id')
                        ->relationship('category', 'name')
                        ->reactive(),

                    Forms\Components\Select::make('measure_units')
                        ->options(MeasureUnit::class)
                        ->preload()
                        ->multiple()
                        ->required(),

                    Forms\Components\Select::make('prod_order_id')
                        ->native(false)
                        ->label('Current prod order')
                        ->options(function($record) {
                            /** @var Collection<ProdOrder> $orders */
                            $orders = ProdOrder::query()
                                ->whereHas('currentStep', fn($q) => $q->where('work_station_id', $record?->id))
                                ->get();

                            $result = [];
                            foreach ($orders as $order) {
                                $result[$order->id] = $order->product->name . ' - ' . $order->warehouse->name . ' - ' . $order->quantity;
                            }
                            return $result;
                        })
                ]),

                Forms\Components\Grid::make(4)->schema([

                    Forms\Components\TextInput::make('performance_qty')
                        ->suffix(function($state, $get) {
                            /** @var ProductCategory $prodCategory */
                            $prodCategory = $get('product_category_id') ? ProductCategory::find(
                                $get('product_category_id')
                            ) : null;
                            return $prodCategory ? $prodCategory->measure_unit->getLabel() : '';
                        }),
                    Forms\Components\TextInput::make('performance_duration'),
                    Forms\Components\Select::make('performance_duration_unit')
                        ->options(DurationUnit::class),

                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function(Builder $query) {
                $query->with('category');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('measure_units')
                    ->formatStateUsing(function(WorkStation $record) {
                        return $record->getMeasureUnitLabels()->implode(', ');
                    }),
                Tables\Columns\TextColumn::make('prod_order_id')
                    ->label('Current prod order')
                    ->formatStateUsing(function($state, $record) {
                        return $record->prodOrder?->product->name . ' - ' . $record->prodOrder?->warehouse->name . ' - ' . $record->prodOrder?->quantity;
                    }),
                //                Tables\Columns\TextColumn::make('type')
                //                    ->numeric()
                //                    ->sortable(),
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\MiniInventoriesRelationManager::class,
            RelationManagers\PerformanceRatesRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkStations::route('/'),
            'create' => Pages\CreateWorkStation::route('/create'),
            'edit' => Pages\EditWorkStation::route('/{record}/edit'),
        ];
    }
}
