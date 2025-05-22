<?php

namespace App\Filament\Resources\ProdOrderGroupResource\Pages;

use App\Filament\Resources\ProdOrderGroupResource;
use App\Filament\Resources\ProdOrderResource;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Services\ProdOrderService;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\View as ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
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
        $this->activeStep = $prodOrder->currentStep;
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

            ViewField::make("step_{$this->activeStep->id}")
                ->view('filament.resources.prod-order-resource.pages.prod-order-step')
                ->viewData(['step' => $this->activeStep])
        ];
    }

    public function handleStepClick($stepId): void
    {
        $this->activeStep = $this->prodOrder->steps->find($stepId);
    }

    public function nextStep(): void
    {
        try {
            $nextStep = app(ProdOrderService::class)->next($this->prodOrder);
            if ($nextStep) {
                $this->activeStep = $nextStep;
            }

            showSuccess('Order has been moved to the next step.');
        } catch (\Exception $e) {
            showError($e->getMessage());
        }
    }

    public function approve(): void
    {
        try {
            app(ProdOrderService::class)->approve($this->prodOrder);

            // back to index page
            $this->redirect(ProdOrderGroupResource::getUrl('details', [
                'record' => $this->prodOrder->group->id,
                'id' => $this->prodOrder->id,
            ]));

            showSuccess('Order has been approved successfully.');
        } catch (\Exception $e) {
            showError($e->getMessage());
        }
    }
}
