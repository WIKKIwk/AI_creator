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

        $message = __('telegram.type') . ": <b>{$poGroup->type->getLabel()}</b>\n";
        $message .= __('telegram.warehouse') . ": <b>{$poGroup->warehouse->name}</b>\n";

        if ($poGroup->type == ProdOrderGroupType::ByOrder) {
            $message .= __('telegram.agent') . ": <b>{$poGroup->agent->partner->name}</b>\n";
        } else {
            $message .= __('telegram.deadline') . ": <b>{$poGroup->deadline->format('d M Y')}</b>\n";
        }

        $message .= __('telegram.progress') . ": <b>{$poGroup->getProgress()}%</b>\n";
        $message .= __('telegram.created_by') . ": <b>{$poGroup->createdBy->name}</b>\n";
        $message .= __('telegram.created_at') . ": <b>{$poGroup->created_at->format('d M Y H:i')}</b>\n";
        $message .= __('telegram.confirmed') . ": $isConfirmed\n";

        $message .= "\n" . __('telegram.products') . ":";
        foreach ($poGroup->prodOrders as $index => $prodOrder) {
            $index++;
            $message .= "\n";
            $message .= TgMessageService::getProdOrderMsg($prodOrder, $index);
        }

        return $message;
    }

    public static function getProdOrderMsg(ProdOrder $prodOrder, $index = null): string
    {
        $message = ($index ? "$index) " : '') . __('telegram.code') . ": <b>$prodOrder->number</b>\n";
        $message .= __('telegram.product') . ": <b>{$prodOrder->product->catName}</b>\n";
        $message .= __('telegram.quantity') . ": <b>$prodOrder->quantity {$prodOrder->product->category->measure_unit->getLabel()}</b>\n";
        $message .= __('telegram.offer_price') . ": <b>$prodOrder->offer_price</b>\n";
        $message .= __('telegram.progress') . ": <b>{$prodOrder->getProgress()}%</b>\n";
        $message .= __('telegram.expected_cost') . ": <b>$prodOrder->total_cost</b>\n";
        $message .= __('telegram.expected_deadline') . ": <b>$prodOrder->deadline days</b>\n";

        $confirmed = $prodOrder->isConfirmed() ? '✅' : '❌';
        $message .= __('telegram.confirmed') . ": $confirmed\n";

        return $message;
    }

    public static function getProdTemplateMsg(ProdTemplate $prodTemplate): string
    {
        $message = "<b>{$prodTemplate->name}</b>\n";
        $message .= __('telegram.ready_product') . ": <b>{$prodTemplate->product->catName}</b>\n";
        $message .= __('telegram.created_at') . ": <b>{$prodTemplate->created_at->format('d M Y H:i')}</b>\n";

        if ($prodTemplate->steps->isNotEmpty()) {
            $message .= "\n<b>" . __('telegram.steps') . ":</b>";
            foreach ($prodTemplate->steps as $index => $step) {
                $index++;
                $message .= "\n";
                $message .= "$index) " . __('telegram.workstation') . ": <b>{$step->workStation->name}</b>\n";
                $message .= __('telegram.output_product') . ": <b>{$step->outputProduct->catName}</b>\n";
                $message .= __('telegram.expected_quantity') . ": <b>{$step->expected_quantity} {$step->outputProduct->getMeasureUnit()->getLabel()}</b>\n";
            }
        } else {
            $message .= "\n<b>" . __('telegram.no_steps_defined') . "</b>\n";
        }

        return $message;
    }

    public static function getPoStepMsg(ProdOrderStep $poStep): string
    {
        $measureUnit = $poStep->outputProduct->getMeasureUnit();

        $message = __('telegram.workstation') . ": <b>" . $poStep->workStation->name . "</b>\n";
        $message .= __('telegram.status') . ": <b>" . $poStep->status->getLabel() . "</b>\n";
        $message .= __('telegram.output_product') . ": <b>" . $poStep->outputProduct->catName . "</b>\n";
        $message .= __('telegram.expected_quantity') . ": <b>" . $poStep->expected_quantity . " " . $measureUnit->getLabel() . "</b>\n";
        $message .= __('telegram.output_quantity') . ": <b>" . $poStep->output_quantity . " " . $measureUnit->getLabel() . "</b>\n";

        return $message;
    }

    public static function getMaterialMsg(ProdOrderStepProduct $material, $index = null): string
    {
        $measureUnit = $material->product->getMeasureUnit();

        $message = '';
        if (!$index) {
            $message .= __('telegram.prod_order') . ": <b>{$material->step->prodOrder->number}</b>\n";
            $message .= __('telegram.step') . ": <b>{$material->step->workStation->name}</b>\n";
        }

        $message .= ($index ? "$index) " : '') . __('telegram.material') . ": <b>{$material->product->catName}</b>\n";
        $message .= __('telegram.required') . ": <b>$material->required_quantity {$measureUnit->getLabel()}</b>\n";
        $message .= __('telegram.available') . ": <b>$material->available_quantity {$measureUnit->getLabel()}</b>\n";
        $message .= __('telegram.used') . ": <b>" . ($material->used_quantity ?? 0) . " {$measureUnit->getLabel()}</b>\n";

        return $message;
    }

    public static function getExecutionMsg(ProdOrderStepExecution $execution): string
    {
        $message = '';
        $message .= __('telegram.prod_order') . ": <b>{$execution->prodOrderStep->prodOrder->number}</b>\n";
        $message .= __('telegram.step') . ": <b>{$execution->prodOrderStep->workStation->name}</b>\n";
        $message .= __('telegram.executed_by') . ": <b>{$execution->executedBy->name}</b>\n";
        $message .= __('telegram.executed_at') . ": <b>{$execution->created_at->format('d M Y H:i')}</b>\n";
        $message .= __('telegram.output_product') . ": <b>{$execution->prodOrderStep->outputProduct->catName}</b>\n";
        $message .= __('telegram.output_quantity') . ": <b>$execution->output_quantity {$execution->prodOrderStep->outputProduct->getMeasureUnit()->getLabel()}</b>\n";
        $message .= __('telegram.notes') . ": <i>$execution->notes</i>\n";

        if (!empty($execution->materials)) {
            $message .= "\n<b>" . __('telegram.used_materials') . "</b>\n";
            foreach ($execution->materials as $index => $material) {
                $index++;
                $message .= "$index) <b>{$material->product->catName}</b>: $material->used_quantity {$material->product->getMeasureUnit()->getLabel()}\n";
            }
        }

        $comments = $execution->getDeclineDetails();

        $commentsAbove = $comments['above'] ?? null;
        if ($commentsAbove) {
            $message .= "\n<b>" . __('telegram.decline_details') . "</b>\n";
            $message .= __('telegram.decline_comment') . ": <i>{$commentsAbove['comment']}</i>\n";
            $message .= __('telegram.declined_by') . ": <i>{$commentsAbove['by']}</i>\n";
            $message .= __('telegram.declined_at') . ": <i>{$commentsAbove['at']}</i>\n";
        }

        $commentsOwn = $comments['own'] ?? null;
        if ($commentsOwn) {
            $message .= "\n" . __('telegram.my_decline_details') . "\n";
            $message .= __('telegram.decline_comment') . ": <i>{$commentsOwn['comment']}</i>\n";
            $message .= __('telegram.declined_at') . ": <i>{$commentsOwn['at']}</i>\n";
        }

        return $message;
    }

    public static function getSupplyOrderMsg(SupplyOrder $supplyOrder, $withProducts = true): string
    {
        $isSupplyManager = in_array(auth()->user()->role, [RoleType::SENIOR_SUPPLY_MANAGER, RoleType::SUPPLY_MANAGER]);

        $isConfirmed = $supplyOrder->isConfirmed() ? '✅' : '❌';

        $message = __('telegram.code') . ": <b>{$supplyOrder->number}</b>\n";
        $message .= __('telegram.warehouse') . ": <b>{$supplyOrder->warehouse->name}</b>\n";
        $message .= __('telegram.category') . ": <b>{$supplyOrder->productCategory->name}</b>\n";
        $message .= __('telegram.supplier') . ": <b>{$supplyOrder->supplier?->partner?->name}</b>\n";
        $message .= __('telegram.status') . ": <b>{$supplyOrder->getStatus()}</b>\n";
        $message .= __('telegram.created_by') . ": <b>{$supplyOrder->createdBy->name}</b>\n";
        $message .= __('telegram.created_at') . ": <b>{$supplyOrder->created_at->format('d M Y H:i')}</b>\n";
        $message .= __('telegram.confirmed') . ": $isConfirmed\n";

        if (!$withProducts) {
            return $message;
        }

        $message .= "\n" . __('telegram.products') . ":";
        foreach ($supplyOrder->products as $index => $product) {
            $index++;

            $warning = '';
            if ($isSupplyManager) {
                $warning = $product->expected_quantity != $product->actual_quantity ? '⚠️' : '';
            }

            $message .= "\n";
            $message .= "$index) $warning " . __('telegram.product') . ": <b>{$product->product->catName}</b>\n";
            if ($isSupplyManager) {
                $message .= __('telegram.expected_quantity') . ": <b>$product->expected_quantity {$product->product->category->measure_unit->getLabel()}</b>\n";
                $message .= __('telegram.actual_quantity') . ": <b>$product->actual_quantity {$product->product->category->measure_unit->getLabel()}</b>\n";
                $message .= __('telegram.price') . ": <b>$product->price</b>\n";
            } else {
                $message .= __('telegram.actual_quantity') . ": <b>$product->actual_quantity {$product->product->category->measure_unit->getLabel()}</b>\n";
            }
        }

        return $message;
    }

    public static function getWorkStationMsg(WorkStation $station): string
    {
        $message = "<b>{$station->name}</b>\n\n";
        $message .= __('telegram.category') . ": <b>{$station->category?->name}</b>\n";
        $message .= __('telegram.organization') . ": <b>{$station->organization->name}</b>\n";
        $message .= __('telegram.type') . ": <b>{$station->type}</b>\n";
        $message .= __('telegram.performance') . ": <b>$station->performance_qty units / $station->performance_duration {$station->performance_duration_unit?->getLabel()}</b>\n";

        $prodOrderName = $station->prodOrder?->number ?? '-';
        $message .= __('telegram.current_prodorder') . ": <b>$prodOrderName</b>\n";

        if ($station->prod_manager_id) {
            $message .= __('telegram.manager') . ": <b>{$station->prodManager->name}</b>\n";
        }

        if (!empty($station->measure_units)) {
            $units = implode(', ', $station->measure_units);
            $message .= __('telegram.measure_units') . ": <b>{$units}</b>\n";
        }

        return $message;
    }
}
