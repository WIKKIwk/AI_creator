<?php

namespace App\Livewire;

use App\Enums\ProdOrderStepStatus;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\Product;
use App\Services\ProdOrderService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

class ProdOrderStepMaterial extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ProdOrderStep $step;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Materials')
            ->paginated(false)
            ->query(
                ProdOrderStepProduct::query()
                    ->with(['product.category'])
                    ->where('prod_order_step_id', $this->step->id)
            )
            ->headerActions([
                Action::make('add')
                    ->label('Add available')
                    ->form($this->changeAvailableForm())
                    ->action(fn ($data, $livewire) => $this->onChangeAvailable($data, $livewire))
                    ->icon('heroicon-o-plus'),
            ])
            ->columns([
                TextColumn::make('product.catName')
                    ->width('300px'),
                TextColumn::make('required_quantity')
                    ->label('Required')
                    ->formatStateUsing(function (ProdOrderStepProduct $record) {
                        return ($record->required_quantity ?? 0) . ' ' . $record->product->category?->measure_unit?->getLabel(
                            );
                    }),
                TextColumn::make('available_quantity')
                    ->label('Available')
                    ->formatStateUsing(function (ProdOrderStepProduct $record) {
                        return ($record->available_quantity ?? 0) . ' ' . $record->product->category?->measure_unit?->getLabel(
                            );
                    }),
                TextColumn::make('used_quantity')
                    ->label('Used')
                    ->getStateUsing(function (ProdOrderStepProduct $record) {
                        return ($record->used_quantity ?? 0) . ' ' . $record->product->category?->measure_unit?->getLabel(
                            );
                    }),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn() => $this->step->status == ProdOrderStepStatus::Completed)
                    ->form($this->changeAvailableForm())
                    ->action(fn ($data, $livewire) => $this->onChangeAvailable($data, $livewire))
            ])
            ->bulkActions([
                // ...
            ]);
    }

    public function changeAvailableForm(): array
    {
        return [
            Grid::make()->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->native(false)
                    ->relationship(
                        'product',
                        'name',
                        fn ($query) => $query->whereIn('id', $this->step->materials()->pluck('product_id')->toArray())
                    )
                    ->getOptionLabelFromRecordUsing(function ($record) {
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
        ];
    }

    public function onChangeAvailable(array $data, self $livewire): void
    {
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
    }

    public function render(): View
    {
        return view('livewire.prod-order-step-required');
    }
}
