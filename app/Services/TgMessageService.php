<?php

namespace App\Services;

use App\Enums\ProdOrderGroupType;
use App\Enums\RoleType;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepExecution;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\ProdTemplate\ProdTemplate;
use App\Models\SupplyOrder\SupplyOrder;
use App\Models\WorkStation;

class TgMessageService
{
    public static function getProdOrderGroupMsg(ProdOrderGroup $poGroup): string
    {
        $isConfirmed = $poGroup->isConfirmed() ? '✅' : '❌';
        $message = "Type: <b>{$poGroup->type->getLabel()}</b>\n";
        $message .= "Warehouse: <b>{$poGroup->warehouse->name}</b>\n";

        if ($poGroup->type == ProdOrderGroupType::ByOrder) {
            $message .= "Agent: <b>{$poGroup->agent->partner->name}</b>\n";
        } else {
            $message .= "Deadline: <b>{$poGroup->deadline->format('d M Y')}</b>\n";
        }

        $message .= "Progress: <b>{$poGroup->getProgress()}%</b>\n";
        $message .= "Created by: <b>{$poGroup->createdBy->name}</b>\n";
        $message .= "Created at: <b>{$poGroup->created_at->format('d M Y H:i')}</b>\n";
        $message .= "Confirmed: $isConfirmed\n";

        $message .= "\nProducts:";
        foreach ($poGroup->prodOrders as $index => $prodOrder) {
            $index++;
            $message .= "\n";
            $message .= TgMessageService::getProdOrderMsg($prodOrder, $index);
        }

        return $message;
    }

    public static function getProdOrderMsg(ProdOrder $prodOrder, $index = null): string
    {
        $message = ($index ? "$index) " : '') . "Code: <b>$prodOrder->number</b>\n";
        $message .= "Product: <b>{$prodOrder->product->catName}</b>\n";
        $message .= "Quantity: <b>$prodOrder->quantity {$prodOrder->product->category->measure_unit->getLabel()}</b>\n";
        $message .= "Offer price: <b>$prodOrder->offer_price</b>\n";
        $message .= "Progress: <b>{$prodOrder->getProgress()}%</b>\n";
        $message .= "Expected cost: <b>$prodOrder->total_cost</b>\n";
        $message .= "Expected deadline: <b>$prodOrder->deadline days</b>\n";

        $confirmed = $prodOrder->isConfirmed() ? '✅' : '❌';
        $message .= "Confirmed: $confirmed\n";

        return $message;
    }

    public static function getProdTemplateMsg(ProdTemplate $prodTemplate): string
    {
        $message = "<b>{$prodTemplate->name}</b>\n";
        $message .= "Ready product: <b>{$prodTemplate->product->catName}</b>\n";
        $message .= "Created at: <b>{$prodTemplate->created_at->format('d M Y H:i')}</b>\n";

        if ($prodTemplate->steps->isNotEmpty()) {
            $message .= "\n<b>Steps:</b>";
            foreach ($prodTemplate->steps as $index => $step) {
                $index++;
                $message .= "\n";
                $message .= "$index) WorkStation: <b>{$step->workStation->name}</b>\n";
                $message .= "Output product: <b>{$step->outputProduct->catName}</b>\n";
                $message .= "Expected quantity: <b>{$step->expected_quantity} {$step->outputProduct->getMeasureUnit()->getLabel()}</b>\n";
            }
        } else {
            $message .= "\n<b>No steps defined</b>\n";
        }

        return $message;
    }

    public static function getPoStepMsg(ProdOrderStep $poStep): string
    {
        $measureUnit = $poStep->outputProduct->getMeasureUnit();

        $message = 'WorkStation: <b>' . $poStep->workStation->name . '</b>' . "\n";
        $message .= 'Status: <b>' . $poStep->status->getLabel() . '</b>' . "\n";
        $message .= 'Output product: <b>' . $poStep->outputProduct->catName . '</b>' . "\n";
        $message .= 'Expected quantity: <b>' . $poStep->expected_quantity . ' ' . $measureUnit->getLabel(
            ) . '</b>' . "\n";
        $message .= 'Output quantity: <b>' . $poStep->output_quantity . ' ' . $measureUnit->getLabel() . '</b>' . "\n";

        return $message;
    }

    public static function getMaterialMsg(ProdOrderStepProduct $material, $index = null): string
    {
        $measureUnit = $material->product->getMeasureUnit();

        $message = '';
        if (!$index) {
            $message .= "Prod order: <b>{$material->step->prodOrder->number}</b>\n";
            $message .= "Step: <b>{$material->step->workStation->name}</b>\n";
        }

        $message .= ($index ? "$index) " : '') . "Material: <b>{$material->product->catName}</b>\n";
        $message .= "Required: <b>$material->required_quantity {$measureUnit->getLabel()}</b>\n";
        $message .= "Available: <b>$material->available_quantity {$measureUnit->getLabel()}</b>\n";
        $message .= "Used: <b>" . ($material->used_quantity ?? 0) . "{$measureUnit->getLabel()}</b>\n";

        return $message;
    }

    public static function getExecutionMsg(ProdOrderStepExecution $execution): string
    {
        $message = "Prod order: <b>{$execution->prodOrderStep->prodOrder->number}</b>\n";
        $message .= "Step: <b>{$execution->prodOrderStep->workStation->name}</b>\n";
        $message .= "Executed by: <b>{$execution->executedBy->name}</b>\n";
        $message .= "Executed at: <b>{$execution->created_at->format('d M Y H:i')}</b>\n";
        $message .= "Output product: <b>{$execution->prodOrderStep->outputProduct->catName}</b>\n";
        $message .= "Output quantity: <b>$execution->output_quantity {$execution->prodOrderStep->outputProduct->getMeasureUnit()->getLabel()}</b>\n";
        $message .= "Notes: <i>$execution->notes</i>\n";

        if (!empty($execution->materials)) {
            $message .= "\n<b>Used materials:</b>\n";
            foreach ($execution->materials as $index => $material) {
                $index++;
                $message .= "$index) <b>{$material->product->catName}</b>: $material->used_quantity {$material->product->getMeasureUnit()->getLabel()}\n";
            }
        }

        return $message;
    }

    public static function getSupplyOrderMsg(SupplyOrder $supplyOrder, $withProducts = true): string
    {
        $isSupplyManager = in_array(auth()->user()->role, [RoleType::SENIOR_SUPPLY_MANAGER, RoleType::SUPPLY_MANAGER]);

        $isConfirmed = $supplyOrder->isConfirmed() ? '✅' : '❌';
        $message = "Code: <b>{$supplyOrder->number}</b>\n";
        $message .= "Warehouse: <b>{$supplyOrder->warehouse->name}</b>\n";
        $message .= "Category: <b>{$supplyOrder->productCategory->name}</b>\n";
        $message .= "Supplier: <b>{$supplyOrder->supplier?->partner?->name}</b>\n";
        $message .= "Status: <b>{$supplyOrder->getStatus()}</b>\n";
        $message .= "Created by: <b>{$supplyOrder->createdBy->name}</b>\n";
        $message .= "Created at: <b>{$supplyOrder->created_at->format('d M Y H:i')}</b>\n";
        $message .= "Confirmed: $isConfirmed\n";

        if (!$withProducts) {
            return $message;
        }

        $message .= "\nProducts:";
        foreach ($supplyOrder->products as $index => $product) {
            $index++;

            $warning = '';
            if ($isSupplyManager) {
                $warning = $product->expected_quantity != $product->actual_quantity ? '⚠️' : '';
            }

            $message .= "\n";
            $message .= "$index) $warning Product: <b>{$product->product->catName}</b>\n";
            if ($isSupplyManager) {
                $message .= "Expected quantity: <b>$product->expected_quantity {$product->product->category->measure_unit->getLabel()}</b>\n";
                $message .= "Actual quantity: <b>$product->actual_quantity {$product->product->category->measure_unit->getLabel()}</b>\n";
                $message .= "Price: <b>$product->price</b>\n";
            } else {
                $message .= "Actual quantity: <b>$product->actual_quantity {$product->product->category->measure_unit->getLabel()}</b>\n";
            }
        }

        return $message;
    }

    public static function getWorkStationMsg(WorkStation $station): string
    {
        $message = "<b>{$station->name}</b>\n\n";
        $message .= "Category: <b>{$station->category?->name}</b>\n";
        $message .= "Organization: <b>{$station->organization->name}</b>\n";
        $message .= "Type: <b>{$station->type}</b>\n";
        $message .= "Performance: <b>$station->performance_qty units / $station->performance_duration {$station->performance_duration_unit?->getLabel()}</b>\n";

        $prodOrderName = $station->prodOrder?->number ?? '-';
        $message .= "Current ProdOrder: <b>$prodOrderName</b>\n";

        if ($station->prod_manager_id) {
            $message .= "Manager: <b>{$station->prodManager->name}</b>\n";
        }

        if (!empty($station->measure_units)) {
            $units = implode(', ', $station->measure_units);
            $message .= "Measure units: <b>{$units}</b>\n";
        }

        return $message;
    }
}
