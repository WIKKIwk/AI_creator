<?php

namespace App\Livewire\ProdOrder;

use App\Enums\ProdOrderStepStatus;
use Throwable;
use App\Models\Product;
use Livewire\Component;
use Illuminate\View\View;
use Filament\Tables\Table;
use App\Services\ProdOrderService;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use App\Models\ProdOrder\ProdOrderStep;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Models\ProdOrder\ProdOrderStepExecution;
use Filament\Tables\Concerns\InteractsWithTable;

class StepExecution extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ProdOrderStep $step;

    protected $listeners = ['refresh-page' => '$refresh'];

    public function getExecutionForm(): array
    {
        return [
            Repeater::make('materials')->schema([
                Grid::make()->schema([
                    Select::make('product_id')
                        ->label('Product')
                        ->native(false)
                        ->options(function() {
                            $step = $this->step;
                            return Product::query()
                                ->whereIn('id', $step->materials()->pluck('product_id')->toArray())
                                ->get()
                                ->pluck('catName', 'id');
                        })
                        ->getOptionLabelFromRecordUsing(function($record) {
                            /** @var Product $record */
                            return $record->ready_product_id ? $record->name : $record->catName;
                        })
                        ->reactive()
                        ->required(),

                    TextInput::make('used_quantity')
                        ->label('Used quantity')
                        ->suffix(function($get) {
                            /** @var Product|null $product */
                            $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                            return $product?->getMeasureUnit()->getLabel();
                        })
                        ->required(),
                ])
            ])
                ->addActionAlignment('end')
                ->columnSpanFull(),

            Grid::make(3)->schema([
                TextInput::make('output_product')
                    ->label('Output product')
                    ->default(function() {
                        return $this->step->outputProduct->name;
                    })
                    ->readOnly(),

                TextInput::make('output_quantity')
                    ->suffix(fn() => $this->step->outputProduct->getMeasureUnit()->getLabel())
                    ->required(),

                TextInput::make('notes'),
            ])
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Executions')
            ->searchable(false)
            ->paginated(false)
            ->query(
                ProdOrderStepExecution::query()
                    ->with(['executedBy', 'materials.product'])
                    ->where('prod_order_step_id', $this->step->id)
            )
            ->headerActions([
                CreateAction::make('add')
                    ->label('Add execution')
                    ->hidden($this->step->status == ProdOrderStepStatus::Completed)
                    ->model(ProdOrderStepExecution::class)
                    ->form($this->getExecutionForm())
                    ->action(function($data) {
                        try {
                            /** @var ProdOrderService $prodOrderService */
                            $prodOrderService = app(ProdOrderService::class);
                            $prodOrderService->createExecution($this->step, $data);

                            showSuccess('Execution created successfully');
                            $this->dispatch('refresh-page');
                        } catch (Throwable $e) {
                            showError($e->getMessage());
                        }
                    })
                    ->icon('heroicon-o-plus'),
            ])
            ->columns([
                TextColumn::make('materials')
                    ->label('Materials')
                    ->formatStateUsing(function(ProdOrderStepExecution $record) {
                        $result = "";
                        foreach ($record->materials as $material) {
                            $result .= "{$material->product->catName}: {$material->used_quantity} {$material->product->getMeasureUnit()->getLabel()}<br>";
                        }
                        return $result;
                    })
                    ->html(),
                TextColumn::make('output_quantity')
                    ->formatStateUsing(function(ProdOrderStepExecution $record) {
                        return ($record->output_quantity ?? 0) . ' ' . $this->step->outputProduct->category?->measure_unit?->getLabel(
                            );
                    }),
                TextColumn::make('notes'),
                TextColumn::make('executedBy.name')
                    ->label('Executed by')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('approved_at')
                    ->disabled(fn(ProdOrderStepExecution $record) => $record->approved_at)
                    ->getStateUsing(function ($record) {
                        if ($record->approved_at) {
                            return '<span class="text-green-500">✔️</span>';
                        }
                        return '<span class="text-red-500">❌</span>';
                    })
                    ->html()
                    ->sortable(),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                Action::make('approve')
                    ->hidden(function (ProdOrderStepExecution $record) {
                        return $record->approved_at || $this->step->status == ProdOrderStepStatus::Completed;
                    })
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->action(function($record) {
                        /** @var ProdOrderService $prodOrderService */
                        $prodOrderService = app(ProdOrderService::class);
                        try {
                            $prodOrderService->approveExecution($record);
                            showSuccess('Execution approved successfully');
                            $this->dispatch('refresh-page');
                        } catch (Throwable $e) {
                            showError($e->getMessage());
                        }
                    })
                    ->label('Approve')
            ])
            ->bulkActions([
                // ...
            ]);
    }

    public function onChangeAvailable(array $data, ProdOrderStepExecution $record, self $livewire): void
    {
        /** @var ProdOrderService $prodOrderService */
        $prodOrderService = app(ProdOrderService::class);
        try {
            $insufficientAssets = $prodOrderService->checkMaterialsExact(
                $this->step,
                $data['product_id'],
                $data['available_quantity']
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
                        $data['available_quantity']
                    ]
                );
            } else {
                $prodOrderService->changeMaterialAvailableExact(
                    $this->step,
                    $data['product_id'],
                    $data['available_quantity']
                );
                showSuccess('Material added successfully');
            }
            $livewire->dispatch('$refresh');
        } catch (Throwable $e) {
            showError($e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.prod-order.step-execution');
    }
}
