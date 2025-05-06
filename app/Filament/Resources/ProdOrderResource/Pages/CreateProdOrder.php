<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\ProdOrderResource;
use App\Services\ProdOrderService;
use Filament\Resources\Pages\CreateRecord;

class CreateProdOrder extends CreateRecord
{
    protected static string $resource = ProdOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = OrderStatus::Pending;
        $data['can_produce'] = false;

        $data['total_cost'] = app(ProdOrderService::class)->calculateTotalCost($data['product_id'], $data['warehouse_id']);
        $data['deadline'] = app(ProdOrderService::class)->calculateDeadline($data['product_id']);

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
