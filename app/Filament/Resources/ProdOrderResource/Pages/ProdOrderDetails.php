<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Enums\OrderStatus;
use Filament\Forms\Components\Tabs;
use App\Filament\Resources\ProdOrderResource;
use App\Models\ProdOrder;
use App\Services\ProdOrderService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

class ProdOrderDetails extends Page
{
    protected static ?string $title = 'Details';
    protected static string $resource = ProdOrderResource::class;

    protected static string $view = 'filament.resources.prod-order-resource.pages.prod-order-details';

    public ProdOrder $prodOrder;
    public $record;

    public function render(): View
    {
        /** @var ProdOrder|null $prodOrder */
        $prodOrder = ProdOrder::query()->find($this->record);
        if (!$prodOrder) {
            abort(404);
        }

        $this->prodOrder = $prodOrder;

        return parent::render();
    }

    public function form(Form $form): Form
    {
        $isViewMode = $this->prodOrder->status == OrderStatus::Completed || $this->prodOrder->status == OrderStatus::Approved;

        $steps = [];
        foreach ($this->prodOrder->steps as $step) {

            if ($isViewMode) {
                $steps[] = Tabs\Tab::make($step->workStation->name)
                    ->schema([
                        Grid::make(1)->schema([
                            Placeholder::make('Order Preview')
                                ->label('')
                                ->content(
                                    fn($record) => view(
                                        'filament.resources.prod-order-resource.pages.prod-order-step',
                                        ['step' => $step]
                                    )
                                ),
                        ]),
                    ]);
            } else {
                $steps[] = Wizard\Step::make($step->workStation->name)
                    ->schema([
                        Grid::make(1)->schema([
                            Placeholder::make('Order Preview')
                                ->label('')
                                ->content(
                                    fn($record) => view(
                                        'filament.resources.prod-order-resource.pages.prod-order-step',
                                        ['step' => $step]
                                    )
                                ),
                        ]),
                    ])
                    ->afterValidation(function ($record) {
                        try {
                            app(ProdOrderService::class)->next($this->prodOrder);

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
                    });
            }

        }

        if ($isViewMode) {
            return $form
                ->schema([
                    Tabs::make()
                        ->activeTab($this->prodOrder->currentStep->sequence)
                        ->schema($steps),
                ]);
        }

        return $form
            ->schema([
                Wizard::make()
                    ->schema($steps)
                    ->startOnStep($this->prodOrder->currentStep->sequence)
                    ->nextAction(fn(Action $action) => $action->label('Complete step'))
                    ->submitAction($this->getSubmitAction()),
            ]);
    }

    public function getSubmitAction(): Htmlable
    {
        return new HtmlString(
            '<button type="submit" style="background: #4f46e5;" class="text-white rounded-lg p-2">Complete step</button>'
        );
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

    public function submit(): void
    {
        try {
            app(ProdOrderService::class)->next($this->prodOrder);

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
