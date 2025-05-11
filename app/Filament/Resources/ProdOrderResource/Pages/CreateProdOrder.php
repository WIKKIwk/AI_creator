<?php

namespace App\Filament\Resources\ProdOrderResource\Pages;

use App\Enums\OrderStatus;
use App\Enums\RoleType;
use App\Enums\TaskAction;
use App\Filament\Resources\ProdOrderResource;
use App\Models\ProdOrder;
use App\Models\SupplyOrder;
use App\Services\ProdOrderService;
use App\Services\TaskService;
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

    protected function afterCreate(): void
    {
        if (auth()->user()->role != RoleType::PRODUCTION_MANAGER) {
            app(TaskService::class)->createTaskForRole(
                toUserRole: RoleType::PRODUCTION_MANAGER,
                relatedType: ProdOrder::class,
                relatedId: $this->record->id,
                action: TaskAction::Confirm,
                comment: 'New production order created. Please confirm the order.',
            );
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
