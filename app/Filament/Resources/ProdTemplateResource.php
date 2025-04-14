<?php

namespace App\Filament\Resources;

use App\Enums\RoleType;
use App\Enums\StepProductType;
use App\Filament\Resources\ProdTemplateResource\Pages;
use App\Filament\Resources\ProdTemplateResource\RelationManagers;
use App\Models\ProdTemplate;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProdTemplateResource extends Resource
{
    protected static ?string $model = ProdTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [
            RoleType::ADMIN,
            RoleType::PLANNING_MANAGER,
        ]);
    }

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
                    ->preload()
                    ->required(),

                Forms\Components\Textarea::make('comment')
                    ->label('Comment')
                    ->columnSpanFull(),

                Forms\Components\Repeater::make('steps')
                    ->relationship('steps')
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
                            $set("steps.$uuid.sequence", $sequence);
                            $sequence++;
                        }
                    })
                    ->schema([
                        Forms\Components\Grid::make()->schema([
                            Forms\Components\Select::make('work_station_id')
                                ->label('Work Station')
                                ->relationship('workStation', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),

                        Forms\Components\Repeater::make('requiredItems')
                            ->columnSpanFull()
                            ->relationship('requiredItems')
                            ->addActionAlignment('end')
                            ->schema([
                                Forms\Components\Hidden::make('type'),
                                Forms\Components\Grid::make()->schema([
                                    Forms\Components\Select::make('product_id')
                                        ->label('Material')
                                        ->relationship('product', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->reactive()
                                        ->required(),
                                    Forms\Components\TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->suffix(function ($get) {
                                            /** @var Product|null $product */
                                            $product = $get('product_id') ? Product::query()->find(
                                                $get('product_id')
                                            ) : null;
                                            if ($product?->measure_unit) {
                                                return $product->measure_unit->getLabel();
                                            }
                                            return null;
                                        })
                                        ->required(),
                                ]),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(function ($data) {
                                $data['type'] = StepProductType::Required;
                                return $data;
                            }),

                        Forms\Components\Repeater::make('expectedItems')
                            ->columnSpanFull()
                            ->relationship('expectedItems')
                            ->addActionAlignment('end')
                            ->schema([
                                Forms\Components\Hidden::make('type'),
                                Forms\Components\Grid::make()->schema([
                                    Forms\Components\Select::make('product_id')
                                        ->label('Result product')
                                        ->relationship('product', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->reactive()
                                        ->required(),
                                    Forms\Components\TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->suffix(function ($get) {
                                            /** @var Product|null $product */
                                            $product = $get('product_id') ? Product::query()->find(
                                                $get('product_id')
                                            ) : null;
                                            if ($product?->measure_unit) {
                                                return $product->measure_unit->getLabel();
                                            }
                                            return null;
                                        })
                                        ->required(),
                                ]),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(function ($data) {
                                $data['type'] = StepProductType::Expected;
                                return $data;
                            }),
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
