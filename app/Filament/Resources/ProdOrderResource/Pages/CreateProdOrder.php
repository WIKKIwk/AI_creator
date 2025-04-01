<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\ProdOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProdOrder extends CreateRecord
{
    protected static string $resource = ProdOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = OrderStatus::Pending;
        $data['can_produce'] = false;

        return parent::mutateFormDataBeforeCreate($data);
    }
}
