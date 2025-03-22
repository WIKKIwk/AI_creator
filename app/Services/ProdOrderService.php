<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StepProductType;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\ProdTemplate;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProdOrderService
{
    /**
     * @throws Exception
     */
    public function start(ProdOrder $prodOrder): void
    {
        $this->guardAlreadyStarted($prodOrder);

        try {
            DB::beginTransaction();

            $prodTemplate = $this->getTemplate($prodOrder->product_id);
            foreach ($prodTemplate->steps as $templateStep) {
                /** @var ProdOrderStep $prodOrderStep */
                $prodOrderStep = $prodOrder->steps()->create([
                    'prod_template_step_id' => $templateStep->id,
                    'work_station_id' => $templateStep->work_station_id,
                    'sequence' => $templateStep->sequence,
                    'status' => OrderStatus::Pending,
                ]);

                foreach ($templateStep->requiredItems as $item) {
                    $prodOrderStep->productItems()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'type' => StepProductType::Required,
                    ]);
                }

                foreach ($templateStep->expectedItems as $item) {
                    $prodOrderStep->productItems()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'type' => StepProductType::Expected,
                    ]);
                }
            }

            $prodOrder->status = OrderStatus::Processing;
            $prodOrder->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function calculateDeadline(ProdTemplate $prodTemplate): ?Carbon
    {
        return Carbon::now();
    }

    /**
     * @throws Exception
     */
    public function calculateTotalCost(ProdTemplate $prodTemplate): ?float
    {
        $totalCost = 0;
        foreach ($prodTemplate->stations as $station) {
            foreach ($station->materialProducts as $material) {
                if ($material->inventories->isEmpty()) {
                    return 0;
                }
                $totalCost += $material->inventories->avg('unit_cost');
            }
        }

        return $totalCost;
    }

    /**
     * @throws Exception
     */
    public function guardAlreadyStarted(ProdOrder $prodOrder): void
    {
        if ($prodOrder->status == OrderStatus::Processing) {
            throw new Exception('Order is already in processing');
        }
    }

    /**
     * @throws Exception
     */
    public function getTemplate($productId): ProdTemplate
    {
        /** @var ProdTemplate $prodTemplate */
        $prodTemplate = ProdTemplate::query()
            ->where('product_id', $productId)
            ->latest()
            ->first();

        if (!$prodTemplate) {
            throw new Exception('No template found for product');
        }

        return $prodTemplate;
    }
}
