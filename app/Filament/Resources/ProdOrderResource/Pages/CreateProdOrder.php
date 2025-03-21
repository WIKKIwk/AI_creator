<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Filament\Resources\ProdOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProdOrder extends CreateRecord
{
    protected static string $resource = ProdOrderResource::class;
}
