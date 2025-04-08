<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Filament\Resources\ProdOrderResource;
use App\Models\ProdOrder;
use App\Services\ProdOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\View as ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ProdOrderDetails extends Page
{
    protected static ?string $title = 'Details';
    protected static string $resource = ProdOrderResource::class;
    protected static string $view = 'filament.resources.prod-order-resource.pages.prod-order-details';

    public ProdOrder $prodOrder;
    public $record;

    public function mount(): void
    {
        /** @var ProdOrder|null $prodOrder */
        $prodOrder = ProdOrder::query()->find($this->record);
        if (!$prodOrder) {
            abort(404);
        }

        $this->prodOrder = $prodOrder;
    }

    protected function getFormSchema(): array
    {
        $tabs = [];
        foreach ($this->prodOrder->steps as $step) {
            $tabs[] = Tabs\Tab::make($step->workStation->name)
                ->schema([
                    ViewField::make("step_$step->id")
                        ->view('filament.resources.prod-order-resource.pages.prod-order-step')
                        ->viewData(['step' => $step]),
                ]);
        }

        return [
            Tabs::make('Work Steps')
                ->tabs($tabs)
                ->activeTab($this->prodOrder->currentStep->sequence)
                ->persistTabInQueryString()
                ->columnSpanFull(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmAction')
                ->label('Next')
                ->requiresConfirmation()
                ->modalHeading('Are you sure?')
                ->modalDescription('This action is final.')
                ->color('primary')
                ->action(fn() => $this->submit()),
        ];
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
