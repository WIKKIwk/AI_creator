<?php

namespace App\Filament\Resources\ProdOrderGroupResource\Pages;

use App\Filament\Resources\ProdOrderGroupResource;
use App\Livewire\ProdOrderStepView;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Page;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Livewire;
use Filament\Resources\Components\Tab;

class ProdOrderView extends Page
{
    protected static string $resource = ProdOrderGroupResource::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox';
    protected static string $view = 'filament.resources.prod-orders.prod-order-view';

    public int $activeTab = 1;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Tabs')
                    ->contained(false)
                    ->activeTab($this->activeTab)
                    ->schema([
                        Tabs\Tab::make('Tab 1')
                            ->schema([
                                Livewire::make(ProdOrderStepView::class)->key('active-tasks-table')
                            ]),
                        Tabs\Tab::make('Tab 2')
                            ->schema([
                                Livewire::make(ProdOrderStepView::class)->key('active-tasks-table-2')->lazy()
                            ]),
                        Tabs\Tab::make('Tab 3')
                            ->schema([
                                Livewire::make(ProdOrderStepView::class)->key('active-tasks-table-3')->lazy()
                            ]),
                    ]),
            ]);
    }
}
