<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Filament\Resources\ProdOrderResource;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStep;
use App\Services\ProdOrderService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View as ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ProdOrderDetails_old extends Page
{
    use InteractsWithForms;

    protected static ?string $title = 'Details';
    protected static string $resource = ProdOrderResource::class;
    protected static string $view = 'filament.resources.prod-order-resource.pages.prod-order-details';

    public $tabs;
    public ProdOrder $prodOrder;
    public $record;
    public ?ProdOrderStep $currentStep;
    public ?ProdOrderStep $activeStep;
    public ?ProdOrderStep $lastStep;

    public function mount(): void
    {
        /** @var ProdOrder|null $prodOrder */
        $prodOrder = ProdOrder::query()->find($this->record);
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
                TextInput::make('output_product_id')
                    ->label('Output Product')
                    ->formatStateUsing(fn ($record) => $this->currentStep->outputProduct?->name)
                    ->readOnly(),

                TextInput::make('expected_quantity')
                    ->formatStateUsing(fn ($record) => $this->currentStep->expected_quantity)
                    ->readOnly(),

                TextInput::make('output_quantity')
                    ->formatStateUsing(fn ($record) => $this->currentStep->expected_quantity)
                    ->readOnly(),
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

            Notification::make()
                ->title('Success')
                ->body('Order has been moved to the next step.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function approve(): void
    {
        try {
            app(ProdOrderService::class)->approve($this->prodOrder);

            // back to index page
            $this->redirect(ProdOrderResource::getUrl('index'));

            Notification::make()
                ->title('Success')
                ->body('Order has been moved to the next step.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
