<?php

namespace App\Filament\Resources\ProdOrderGroupResource\Pages;

use App\Enums\ProdOrderStepStatus;
use App\Filament\Resources\ProdOrderGroupResource;
use App\Livewire\ProdOrder\StepExecution;
use App\Livewire\ProdOrder\StepMaterial;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStep;
use App\Services\ProdOrderService;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ProdOrderDetails extends Page
{
    use InteractsWithForms;

    protected static ?string $title = 'Details';
    protected static string $resource = ProdOrderGroupResource::class;
    protected static string $view = 'filament.resources.prod-order-resource.pages.prod-order-details';

    public function getTitle(): string|Htmlable
    {
        return 'Details ' . $this->prodOrder->number;
    }

    public $tabs;
    public ProdOrder $prodOrder;
    public $record;
    public $id;
    public ?ProdOrderStep $currentStep;
    public ?ProdOrderStep $activeStep;
    public ?ProdOrderStep $lastStep;

    protected $listeners = ['refresh-page' => '$refresh'];

    public function getBreadcrumb(): ?string
    {
        return $this->prodOrder->number;
    }

    public function getBreadcrumbs(): array
    {
        return [
            ProdOrderGroupResource::getUrl('edit', ['record' => $this->record]) => 'Details',
            null => $this->prodOrder->number,
        ];
    }

    public function mount(): void
    {
        /** @var ProdOrder|null $prodOrder */
        $prodOrder = ProdOrder::query()->find($this->id);
        if (!$prodOrder) {
            abort(404);
        }

        $this->prodOrder = $prodOrder;

        $activeStep = $prodOrder->steps()
            ->where('status', '!=', ProdOrderStepStatus::Completed)
            ->orderBy('sequence')
            ->first();

        $this->activeStep = $activeStep ?? $prodOrder->firstStep;
        $this->currentStep = $prodOrder->currentStep;
        $this->lastStep = $prodOrder->steps->last() ?? null;
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make(3)->schema([
                Fieldset::make('Step Details')
                    ->columns(4)
                    ->schema([
                        Placeholder::make('ads')
                            ->label('Output Product')
                            ->content(fn() => $this->activeStep->outputProduct?->name),

                        Placeholder::make('expected_quantity')
                            ->content(function () {
                                return $this->activeStep->expected_quantity . ' ' . $this->activeStep->outputProduct?->category?->measure_unit?->getLabel(
                                    );
                            }),

                        Placeholder::make('output_quantity')
                            ->content(function () {
                                return ($this->activeStep->output_quantity ?? 0) . ' ' . $this->activeStep->outputProduct?->category?->measure_unit?->getLabel(
                                    );
                            }),

                        Placeholder::make('status')
                            ->label('Status')
                            ->content(fn() => $this->activeStep->status?->getLabel()),
                    ]),
            ]),

            Livewire::make(StepMaterial::class)
                ->key("step-materials-{$this->activeStep->id}")
                ->data([
                    'step' => $this->activeStep,
                    'prodOrder' => $this->prodOrder,
                ]),

            Livewire::make(StepExecution::class)
                ->key("step-executions-{$this->activeStep->id}")
                ->data([
                    'step' => $this->activeStep,
                    'prodOrder' => $this->prodOrder,
                ]),
        ];
    }

    public function handleStepClick($stepId): void
    {
        $this->activeStep = $this->prodOrder->steps->find($stepId);
    }
}
