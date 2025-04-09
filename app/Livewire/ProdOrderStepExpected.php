<?php

namespace App\Livewire;

use App\Enums\ProdOrderProductStatus;
use App\Enums\StepProductType;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\ProdOrderStepProduct;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\View\View;
use Livewire\Component;

class ProdOrderStepExpected extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ProdOrderStep $step;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Expected materials')
            ->paginated(false)
            ->query(
                ProdOrderStepProduct::query()
                    ->with(['product'])
                    ->where('prod_order_step_id', $this->step->id)
                    ->where('type', StepProductType::Expected)
            )
            ->columns([
                TextColumn::make('product.name'),
                TextColumn::make('quantity')
                    ->formatStateUsing(function (ProdOrderStepProduct $record) {
                        return $record->quantity . ' ' . $record->product->measure_unit->getLabel();
                    })
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
        return view('livewire.prod-order-step-expected');
    }
}
