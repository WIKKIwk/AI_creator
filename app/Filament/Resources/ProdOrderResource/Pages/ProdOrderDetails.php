<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Filament\Resources\ProdOrderResource;
use Filament\Resources\Pages\Page;

class ProdOrderDetails extends Page
{
    protected static string $resource = ProdOrderResource::class;

    protected static string $view = 'filament.resources.prod-order-resource.pages.prod-order-details';
}
