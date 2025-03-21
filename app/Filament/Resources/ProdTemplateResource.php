<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdTemplateResource\Pages;
use App\Filament\Resources\ProdTemplateResource\RelationManagers;
use App\Models\ProdTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProdTemplateResource extends Resource
{
    protected static ?string $model = ProdTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Name'),
                Forms\Components\Select::make('product_id')
                    ->label('Product')
                    ->native(false)
                    ->relationship('product', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\Textarea::make('comment')
                    ->label('Comment')
                    ->columnSpanFull(),

                Forms\Components\Repeater::make('stations')
                    ->relationship('stations')
                    ->columnSpanFull()
                    ->addActionAlignment('end')
                    ->reorderable()
                    ->orderColumn('sequence')
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(fn($state) => "Station " . ($state['sequence'] ?? 1))
                    ->afterStateUpdated(function ($set, $state) {
                        $sequence = 1;
                        foreach ($state as $uuid => $item) {
                            $item['sequence'] = $sequence;
                            $set("stations.$uuid.sequence", $sequence);
                            $sequence++;
                        }
                    })
                    ->schema([
                        Forms\Components\Grid::make()->schema([
//                            Forms\Components\TextInput::make('sequence')
//                                ->label('Sequence')
//                                ->numeric(),
                            Forms\Components\Select::make('work_station_id')
                                ->label('Work Station')
                                ->relationship('workStation', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),

                        Forms\Components\Repeater::make('materials')
                            ->columnSpanFull()
                            ->relationship('materials')
                            ->addActionAlignment('end')
                            ->schema([
                                Forms\Components\Grid::make()->schema([
                                    Forms\Components\Select::make('material_product_id')
                                        ->label('Material')
                                        ->relationship('materialProduct', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    Forms\Components\TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->required(),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->searchable()
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
            //
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
