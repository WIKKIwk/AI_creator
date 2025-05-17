<?php

namespace App\Filament\Resources\ProdTemplateResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use App\Models\Product;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Enums\ProductType;
use App\Enums\MeasureUnit;
use App\Models\WorkStation;
use App\Models\ProdTemplate;
use App\Enums\StepProductType;
use App\Models\ProdTemplateStep;
use App\Services\ProductService;
use Filament\Resources\RelationManagers\RelationManager;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('work_station_id')
                    ->label('Work Station')
                    ->relationship('workStation', 'name')
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->required(),

                Forms\Components\Checkbox::make('is_last')
                    ->inline(false)
                    ->reactive()
                    ->afterStateUpdated(function($set, $get) {
                        /** @var ProdTemplate $prodTmp */
                        $prodTmp = $this->getOwnerRecord();
                        if ($get('is_last')) {
                            $set('output_product_id', $prodTmp->product_id);
                        } else {
                            $workStation = $get('work_station_id') ? WorkStation::find($get('work_station_id')) : null;
                            if ($workStation && $prodTmp->product) {
                                $sfpProduct = Product::query()
                                    ->where('name', ProductService::getSfpName($prodTmp->product, $workStation))
                                    ->first();
                                $set('output_product_id', $sfpProduct?->id);
                            } else {
                                $set('output_product_id', null);
                            }
                        }
                    }),

                Forms\Components\Grid::make(3)->schema([
//                    Forms\Components\TextInput::make('output_product')
//                        ->label('Output product')
//                        ->readOnly()
//                        ->formatStateUsing(function($record) {
//                            /** @var ProdTemplateStep $record */
//                            if ($record?->outputProduct) {
//                                return $record->outputProduct->getDisplayName();
//                            } else {
//                                return "-";
//                            }
//                        })
//                        ->reactive(),

                Forms\Components\Select::make('output_product_id')
                    ->label('Output product')
                    ->relationship('outputProduct', 'name')
                    ->getOptionLabelFromRecordUsing(function($record) {
                        /** @var Product $record */
                        return $record->ready_product_id ? $record->name : ($record->category->name . ' ' . $record->name);
                    })
                    ->afterStateUpdated(function ($set, $get) {
                        $set('is_last', $get('output_product_id') == $this->getOwnerRecord()->product_id);
                    })
                    ->reactive()
                    ->searchable()
                    ->preload(),

                    Forms\Components\Select::make('measure_unit')
                        ->label('Measure unit')
                        ->options(function($record, $get) {
                            $result = [];

                            if ($record?->workStation) {
                                $workStation = $record->workStation;
                            } else {
                                $workStation = $get('work_station_id') ? WorkStation::find($get('work_station_id')) : null;
                            }

                            foreach ($workStation?->getMeasureUnits() ?? [] as $item) {
                                $result[$item->value] = $item->getLabel();
                            }
                            return $result;
                        })
                        ->required()
                        ->reactive(),

                    Forms\Components\TextInput::make('expected_quantity')
                        ->label('Expected quantity')
                        ->suffix(function($get) {
                            /** @var ?WorkStation $workStation */
                            $workStation = $get('work_station_id') ? WorkStation::find($get('work_station_id')) : null;
                            return $workStation?->category?->measure_unit?->getLabel();
                        })
                        ->required()
                        ->reactive(),
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
                                ->relationship(
                                    'product',
                                    'name',
                                    fn($query) => $query->whereNot('type', ProductType::ReadyProduct)
                                )
                                ->getOptionLabelFromRecordUsing(function($record) {
                                    /** @var Product $record */
                                    return $record->ready_product_id ? $record->name : ($record->category->name . ' ' . $record->name);
                                })
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->required(),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->suffix(function($get) {
                                    /** @var Product|null $product */
                                    $product = $get('product_id') ? Product::query()->find(
                                        $get('product_id')
                                    ) : null;
                                    return $product?->category?->measure_unit?->getLabel();
                                })
                                ->required(),
                        ]),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(function($data) {
                        $data['type'] = StepProductType::Required;
                        return $data;
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
//            ->reorderable('sequence')
            ->paginated(false)
            ->defaultSort('sequence')
            ->recordTitleAttribute('work_station_id')
            ->modifyQueryUsing(function($query) {
                $query->with(['workStation', 'outputProduct']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('sequence'),
                Tables\Columns\TextColumn::make('workStation.name'),
                Tables\Columns\TextColumn::make('outputProduct.name'),
                Tables\Columns\TextColumn::make('expected_quantity')
                    ->label('Expected quantity')
                    ->formatStateUsing(function($record) {
                        /** @var ProdTemplateStep $record */
                        return $record->expected_quantity . ' ' . $record->measure_unit?->getLabel(
                            );
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add step')
                    ->mutateFormDataUsing(function($data) {
                        /** @var ProdTemplate $prodTemplate */
                        $prodTemplate = $this->getOwnerRecord();

                        if (!$data['output_product_id']) {
                            /** @var ProductService $productService */
                            $productService = app(ProductService::class);
                            $outputProduct = $productService->createOrGetSemiFinished(
                                $prodTemplate,
                                $data['work_station_id'] ?? null,
                                $data['is_last'] ?? false,
                            );
                            $data['output_product_id'] = $outputProduct?->id;
                        }

                        $data['sequence'] = $prodTemplate->steps()->count() + 1;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function($data) {
                        /** @var ProdTemplate $prodTemplate */
                        $prodTemplate = $this->getOwnerRecord();

                        if (!$data['output_product_id']) {
                            /** @var ProductService $productService */
                            $productService = app(ProductService::class);
                            $outputProduct = $productService->createOrGetSemiFinished(
                                $prodTemplate,
                                $data['work_station_id'] ?? null,
                                $data['is_last'] ?? false,
                            );
                            $data['output_product_id'] = $outputProduct?->id;
                        }
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
