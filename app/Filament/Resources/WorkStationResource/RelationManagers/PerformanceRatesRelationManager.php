<?php

namespace App\Filament\Resources\WorkStationResource\RelationManagers;

use App\Enums\DurationUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PerformanceRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'performanceRates';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\Select::make('product_id')
                        ->native(false)
                        ->relationship('product', 'name')
                        ->reactive()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('quantity')
                        ->required(),
                    Forms\Components\TextInput::make('duration')
                        ->required(),
                    Forms\Components\Select::make('duration_unit')
                        ->options(DurationUnit::class)
                        ->required(),
                ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_id')
            ->modifyQueryUsing(function (Builder $query) {
                $query->with('product');
            })
            ->columns([
                Tables\Columns\TextColumn::make('product.name'),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->formatStateUsing(function ($record) {
                        return $record->duration . ' ' . $record->duration_unit->getLabel();
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->formatStateUsing(function ($record) {
                        return $record->quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    })
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
