<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\ProdOrderResource;
use App\Services\ProdOrderService;
use Exception;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProdOrder extends EditRecord
{
    protected static string $resource = ProdOrderResource::class;

    /**
     * @throws Exception
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->status == OrderStatus::Pending) {
            throw new Exception('Cannot edit a pending order.');
        }

        $data['total_cost'] = app(ProdOrderService::class)->calculateTotalCost($data['product_id'], $data['warehouse_id']);
        $data['deadline'] = app(ProdOrderService::class)->calculateDeadline($data['product_id']);

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
