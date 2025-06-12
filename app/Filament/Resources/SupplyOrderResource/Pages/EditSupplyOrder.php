<?php

namespace App\Filament\Resources\SupplyOrderResource\Pages;

use App\Enums\RoleType;
use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Enums\TaskAction;
use App\Filament\Resources\SupplyOrderResource;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\SupplyOrder\SupplyOrderStep;
use App\Services\SupplyOrderService;
use App\Services\TaskService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Throwable;

class EditSupplyOrder extends EditRecord
{
    protected static string $resource = SupplyOrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var SupplyOrder $supplyOrder */
        /** @var SupplyOrderStep $lastStep */
        $supplyOrder = $this->record;

        $state = $data['state'] ?? SupplyOrderState::Closed->value;
        $status = $data['custom_status'] ?? $data['status'] ?? null;

        try {
            DB::beginTransaction();

            $attributes = $supplyOrder->changeStatus($state, $status);
            $data = array_merge($data, $attributes);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            showError('Failed to update order state: ' . $e->getMessage());
        }

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\Action::make('Close order')
                ->visible(function() {
                    return $this->record->isReadyForClose();
                })
                ->action(function () {
                    try {
                        /** @var SupplyOrderService $supplyService */
                        $supplyService = app(SupplyOrderService::class);
                        $supplyService->closeOrder($this->record);
                        showSuccess('Order closed successfully');
                        $this->dispatch('refresh-page');
                    } catch (Throwable $e) {
                        showError('Failed to close order: ' . $e->getMessage());
                    }
                })
                ->requiresConfirmation()
                ->color('info')
                ->icon('heroicon-o-check-circle'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
