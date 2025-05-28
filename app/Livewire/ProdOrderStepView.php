<?php

namespace App\Livewire;

use App\Enums\ProdOrderStepStatus;
use App\Enums\RoleType;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\Product;
use App\Services\ProdOrderService;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View as ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

class ProdOrderStepView extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ProdOrderStep $step;

    protected function getFormSchema(): array
    {
        return [
            Grid::make(3)->schema([
                Fieldset::make('Step Details')
                    ->columns(4)
                    ->schema([
                        Placeholder::make('ads')
                            ->label('Output Product')
                            ->content(fn() => '$this->activeStep->outputProduct?->name'),

                        Placeholder::make('expected_quantity')
                            ->content(function () {
                                return '$this->activeStep->expected_quantity';
                            }),

                        Placeholder::make('output_quantity')
                            ->content(function () {
                                return 'asd';
                            }),

                        Placeholder::make('status')
                            ->label('Status')
                            ->content(fn() => '$this->activeStep->status?->getLabel()'),
                    ]),
            ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Actual materials')
            ->headerActions([
                Action::make('add')
                    ->label('Add material')
                    ->form([
                        Grid::make()->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->native(false)
                                ->relationship('product', 'name')
                                ->getOptionLabelFromRecordUsing(function($record) {
                                    /** @var Product $record */
                                    return $record->ready_product_id ? $record->name : $record->catName;
                                })
                                ->searchable()
                                ->reactive()
                                ->preload()
                                ->required(),

                            TextInput::make('max_quantity')
                                ->label('Available quantity')
                                ->suffix(function ($get) {
                                    /** @var Product|null $product */
                                    $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                                    return $product?->getMeasureUnit()->getLabel();
                                })
                                ->required(),
                        ])
                    ])
                    ->action(function ($data, $livewire) {
                        /** @var ProdOrderService $prodOrderService */
                        $prodOrderService = app(ProdOrderService::class);
                        try {
                            $insufficientAssets = $prodOrderService->checkMaterials(
                                $this->step,
                                $data['product_id'],
                                $data['max_quantity']
                            );
                            if (!empty($insufficientAssets)) {
                                $livewire->dispatch(
                                    'openModal',
                                    $this->step->prodOrder,
                                    $insufficientAssets,
                                    'editMaterials',
                                    [
                                        $this->step->id,
                                        $data['product_id'],
                                        $data['max_quantity']
                                    ]
                                );
                            } else {
                                $prodOrderService->editMaterials(
                                    $this->step,
                                    $data['product_id'],
                                    $data['max_quantity']
                                );
                                showSuccess('Material added successfully');
                            }
                            $livewire->dispatch('$refresh');
                        } catch (Throwable $e) {
                            showError($e->getMessage());
                        }
                    })
                    ->icon('heroicon-o-plus'),
            ])
            ->paginated(false)
            ->query(
                ProdOrderStepProduct::query()
                    ->with(['product.category'])
            )
            ->columns([
                TextColumn::make('product.catName')
                    ->width('500px'),
                TextColumn::make('max_quantity')
                    ->label('Available quantity')
                    ->formatStateUsing(function (ProdOrderStepProduct $record) {
                        return $record->available_quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    }),
                TextColumn::make('quantity')
                    ->label('Used quantity')
                    ->formatStateUsing(function (ProdOrderStepProduct $record) {
                        return $record->required_quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    }),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        Grid::make()->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->native(false)
                                ->relationship('product', 'name')
                                ->getOptionLabelFromRecordUsing(function($record) {
                                    /** @var Product $record */
                                    return $record->ready_product_id ? $record->name : $record->catName;
                                })
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->required(),

                            TextInput::make('max_quantity')
                                ->label('Available quantity')
                                ->suffix(function ($get) {
                                    /** @var Product|null $product */
                                    $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                                    return $product?->getMeasureUnit()->getLabel();
                                })
                                ->required(),
                        ])
                    ])
                    ->action(function ($data, $livewire) {
                        /** @var ProdOrderService $prodOrderService */
                        $prodOrderService = app(ProdOrderService::class);
                        try {
                            $insufficientAssets = $prodOrderService->checkMaterials(
                                $this->step,
                                $data['product_id'],
                                $data['max_quantity']
                            );
                            if (!empty($insufficientAssets)) {
                                $livewire->dispatch(
                                    'openModal',
                                    $this->step->prodOrder,
                                    $insufficientAssets,
                                    'editMaterials',
                                    [
                                        $this->step->id,
                                        $data['product_id'],
                                        $data['max_quantity']
                                    ]
                                );
                            } else {
                                $prodOrderService->editMaterials(
                                    $this->step,
                                    $data['product_id'],
                                    $data['max_quantity']
                                );
                                showSuccess('Material edited successfully');
                            }
                            $livewire->dispatch('$refresh');
                        } catch (Throwable $e) {
                            showError($e->getMessage());
                        }
                    })
            ])
            ->bulkActions([
                // ...
            ]);
    }

    public function render(): View
    {
        return view('filament.resources.prod-orders.prod-order-step-view');
    }
}
