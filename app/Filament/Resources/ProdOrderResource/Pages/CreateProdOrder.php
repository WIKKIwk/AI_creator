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

//        $productId = $data['product_id'];
//        $prodTemplate = app(ProdOrderService::class)->getTemplate($productId);
//        $data['total_cost'] = app(ProdOrderService::class)->calculateTotalCost($prodTemplate);
//        $data['deadline'] = app(ProdOrderService::class)->calculateDeadline($prodTemplate);

        return parent::mutateFormDataBeforeCreate($data);
    }
}
