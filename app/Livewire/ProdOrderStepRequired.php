<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\View\View;
use Filament\Tables\Table;
use App\Models\ProdOrderStep;
use App\Enums\StepProductType;
use App\Models\ProdOrderStepProduct;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

class ProdOrderStepRequired extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ProdOrderStep $step;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Required materials')
            ->paginated(false)
            ->query(
                ProdOrderStepProduct::query()
                    ->with(['product.category'])
                    ->where('prod_order_step_id', $this->step->id)
                    ->where('type', StepProductType::Required)
            )
            ->columns([
                TextColumn::make('product.catName')
                    ->width('500px'),
                TextColumn::make('quantity')
                    ->formatStateUsing(function(ProdOrderStepProduct $record) {
                        return $record->required_quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
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
