<?php

namespace App\Livewire;

use App\Enums\ProdOrderStepStatus;
use App\Enums\StepProductType;
use App\Models\ProdOrderStep;
use App\Models\ProdOrderStepProduct;
use App\Models\Product;
use App\Services\ProdOrderService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

class ProdOrderStepActual extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ProdOrderStep $step;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Actual materials')
            ->headerActions([
                Action::make('add')
                    ->label('Add material')
                    ->hidden(fn() => $this->step->status == ProdOrderStepStatus::Completed)
                    ->form([
                        Grid::make()->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->native(false)
                                ->relationship('product', 'name')
                                ->searchable()
                                ->reactive()
                                ->required(),

                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->suffix(function ($get) {
                                    /** @var Product|null $product */
                                    $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                                    if ($product?->category?->measure_unit) {
                                        return $product->category->measure_unit->getLabel();
                                    }
                                    return null;
                                })
                                ->required(),
                        ])
                    ])
                    ->action(function ($data) {
                        try {
                            app(ProdOrderService::class)->editMaterials(
                                $this->step,
                                $data['product_id'],
                                $data['quantity']
                            );

                            Notification::make()
                                ->title('Success')
                                ->body('Material added successfully')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->icon('heroicon-o-plus'),
            ])
            ->paginated(false)
            ->query(
                ProdOrderStepProduct::query()
                    ->with(['product'])
                    ->where('prod_order_step_id', $this->step->id)
                    ->where('type', StepProductType::Actual)
            )
            ->columns([
                TextColumn::make('product.name')
                    ->width('500px'),
                TextColumn::make('quantity')
                    ->formatStateUsing(function (ProdOrderStepProduct $record) {
                        return $record->quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    }),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                EditAction::make()
                    //->hidden(fn() => $this->step->status == ProdOrderStepStatus::Completed)
                    ->form([
                        Grid::make()->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->native(false)
                                ->relationship('product', 'name')
                                ->searchable()
                                ->reactive()
                                ->required(),

                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->suffix(function ($get) {
                                    /** @var Product|null $product */
                                    $product = $get('product_id') ? Product::query()->find($get('product_id')) : null;
                                    if ($product?->category?->measure_unit) {
                                        return $product->category->measure_unit->getLabel();
                                    }
                                    return null;
                                })
                                ->required(),
                        ])
                    ])
                    ->action(function ($data) {
                        try {
                            app(ProdOrderService::class)->editMaterials(
                                $this->step,
                                $data['product_id'],
                                $data['quantity']
                            );

                            Notification::make()
                                ->title('Success')
                                ->body('Material updated successfully')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
            ])
            ->bulkActions([
                // ...
            ]);
    }

    public function render(): View
    {
        return view('livewire.prod-order-step-required');
    }
}
