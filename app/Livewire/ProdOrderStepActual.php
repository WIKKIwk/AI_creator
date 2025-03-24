<?php

namespace App\Livewire;

use App\Enums\StepProductType;
use App\Models\ProdOrder;
use App\Models\ProdOrderStepProduct;
use App\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\View\View;
use Livewire\Component;

class ProdOrderStepActual extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ProdOrder $prodOrder;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Actual materials')
            ->paginated(false)
            ->query(
                ProdOrderStepProduct::query()
                    ->with(['product'])
                    ->where('prod_order_step_id', $this->prodOrder->current_step_id)
                    ->where('type', StepProductType::Actual)
            )
            ->columns([
                TextColumn::make('product.name'),
                TextColumn::make('quantity')
                    ->formatStateUsing(function (ProdOrderStepProduct $record) {
                        return $record->quantity . ' ' . $record->product->measure_unit->getLabel();
                    }),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                // ...
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
