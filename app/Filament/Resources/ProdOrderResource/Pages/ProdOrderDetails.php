<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Filament\Resources\ProdOrderResource;
use App\Models\ProdOrder;
use App\Services\ProdOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;

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
        $steps = [];
        foreach ($this->prodOrder->steps as $step) {
            $steps[] = Tabs\Tab::make($step->workStation->name)
                ->schema([
                    Placeholder::make('Order Preview')
                        ->label('')
                        ->content(
                            fn($record) => view(
                                'filament.resources.prod-order-resource.pages.prod-order-step',
                                ['step' => $step]
                            )
                        ),
                ]);
        }

        return $form
            ->schema([
                Tabs::make()
                    ->activeTab($this->prodOrder->currentStep->sequence)
                    ->schema($steps),
            ]);
    }

    protected function getActions(): array
    {
        return [
            /*ProdOrderResource\Actions\CompleteStepAction::make('complete')
                ->button()
                ->label('Submit')
                ->color('primary')
                ->requiresConfirmation(),*/
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => $this->submit()),
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
