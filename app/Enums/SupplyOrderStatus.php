<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SupplyOrderStatus: string implements HasLabel, HasColor, HasIcon
{
    case SupplyDep = 'supply_dep';
    case AwaitingWarehouseApproval = 'awaiting_warehouse_approval';
    case AwaitingSupplierApproval = 'awaiting_supplier_approval';

    public function getLabel(): string
    {
        return match ($this) {
            self::SupplyDep => 'Supply Department',
            self::AwaitingWarehouseApproval => 'Awaiting Warehouse Approval',
            self::AwaitingSupplierApproval => 'Awaiting Supplier Approval',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SupplyDep => 'success',
            self::AwaitingWarehouseApproval => 'warning',
            self::AwaitingSupplierApproval => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SupplyDep => 'heroicon-o-check-circle',
            self::AwaitingWarehouseApproval, self::AwaitingSupplierApproval => 'heroicon-o-exclamation',
        };
    }
}
