<?php

namespace App\Filament\Resources\InventoryResource\Actions;

use Closure;
use Filament\Actions\StaticAction;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class InventoryItemsAction extends Action
{
    protected ?Closure $mutateRecordDataUsing = null;

    public static function getDefaultName(): ?string
    {
        return 'inventory_items_action';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Inventory Items');

        $this->modalHeading(fn ($record) => $record->name);

//        $this->modalSubmitAction(fn (StaticAction $action, $record) => $action->url(route('filament.admin.resources.tour-tps.edit', $record->id))->label('Edit'));
//        $this->modalCancelAction(fn (StaticAction $action) => $action->label(__('filament-actions::view.single.modal.actions.close.label')));

        $this->modalWidth(MaxWidth::ExtraLarge);

        $this->modalContent(function (Model $record, Table $table) {
            return view('actions.inventory_items', [
                'record' => $record,
                'table' => $table,
            ]);
        });

        $this->color('gray');

        $this->icon(FilamentIcon::resolve('actions::view-action') ?? 'heroicon-m-eye');

        $this->disabledForm();

        $this->fillForm(function (Model $record, Table $table): array {
            if ($translatableContentDriver = $table->makeTranslatableContentDriver()) {
                $data = $translatableContentDriver->getRecordAttributesToArray($record);
            } else {
                $data = $record->attributesToArray();
            }

            if ($this->mutateRecordDataUsing) {
                $data = $this->evaluate($this->mutateRecordDataUsing, ['data' => $data]);
            }

            return $data;
        });

        $this->action(null);
    }

    public function mutateRecordDataUsing(?Closure $callback): static
    {
        $this->mutateRecordDataUsing = $callback;

        return $this;
    }
}
