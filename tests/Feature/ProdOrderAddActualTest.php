<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StepProductType;
use App\Models\Inventory;
use App\Models\MiniInventory;
use App\Models\ProdOrder;
use App\Models\ProdOrderStep;
use App\Models\ProdTemplate;
use App\Models\ProdTemplateStep;
use App\Models\Product;
use App\Models\WorkStation;
use App\Services\ProdOrderService;
use App\Services\WorkStationService;
use Exception;
use Filament\Forms\Components\Wizard\Step;
use Tests\Feature\HasProdTemplate;
use Tests\TestCase;

class ProdOrderAddActualTest extends TestCase
{
    use HasProdTemplate;

    protected ProdOrder $prodOrder;
    protected ProdOrderService $prodOrderService;
    protected WorkStationService $workStationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createProdTemplate();

        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::query()->create([
            'agent_id' => $this->agent->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->readyProduct->id,
            'quantity' => 3,
            'offer_price' => 100,
            'status' => OrderStatus::Pending
        ]);
        $this->prodOrder = $prodOrder;

        $this->prodOrderService = app(ProdOrderService::class);
        $this->workStationService = app(WorkStationService::class);
    }

    /**
     * @dataProvider lessMoreQtyProvider
     */
    public function test_add_actual_materials($qty): void
    {
        $anotherProduct = $this->createProduct(['name' => 'Another Product']);
        $this->actingAs($this->user);

        /** @var Inventory $inventory */
        $inventory = Inventory::query()->create([
            'warehouse_id' => $this->prodOrder->warehouse_id,
            'product_id' => $anotherProduct->id,
            'quantity' => $stockQty = 5,
            'unit_cost' => 100,
        ]);
        $inventoryItem = $inventory->items()->create(['quantity' => 5]);

        /** @var ProdOrderStep $step */
        $step = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'status' => OrderStatus::Pending,
        ]);

        $insufficientQty = $qty > $stockQty;
        if ($insufficientQty) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Insufficient stock');
        }

        $this->prodOrderService->editMaterials($step, $anotherProduct->id, $qty);

        if ($insufficientQty) {
            $this->assertDatabaseMissing('prod_order_step_products', [
                'product_id' => $anotherProduct->id,
                'type' => StepProductType::Actual
            ]);
            $this->assertDatabaseHas('inventory_items', [
                'id' => $inventoryItem->id,
                'quantity' => $stockQty,
            ]);
            $this->assertDatabaseMissing('mini_inventories', [
                'work_station_id' => $this->workStationFirst->id,
            ]);
        } else {
            $this->assertDatabaseHas('prod_order_step_products', [
                'product_id' => $anotherProduct->id,
                'quantity' => $qty,
                'max_quantity' => $qty,
                'type' => StepProductType::Actual
            ]);
            $this->assertDatabaseHas('inventory_items', [
                'id' => $inventoryItem->id,
                'quantity' => $stockQty - $qty,
            ]);
            $this->assertDatabaseHas('mini_inventories', [
                'work_station_id' => $this->workStationFirst->id,
                'product_id' => $anotherProduct->id,
                'quantity' => $qty,
            ]);
        }
    }

    /**
     * @dataProvider lessMoreQtyProvider
     */
    public function test_edit_actual_materials($qty): void
    {
        $this->actingAs($this->user);

        /** @var Inventory $inventory */
        $inventory = Inventory::query()->create([
            'warehouse_id' => $this->prodOrder->warehouse_id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => $stockQty = 5,
            'unit_cost' => 100,
        ]);
        $inventoryItem = $inventory->items()->create(['quantity' => 5]);

        /** @var ProdOrderStep $step */
        $step = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'work_station_id' => $this->workStationFirst->id,
            'status' => OrderStatus::Pending,
        ]);

        $actualItem = $step->productItems()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => $prevQty = 6,
            'max_quantity' => $prevQty,
            'type' => StepProductType::Actual
        ]);

        /** @var MiniInventory $miniStock */
        $miniStock = $this->workStationFirst->miniInventories()->create([
            'product_id' => $this->rawMaterial->id,
            'quantity' => $prevQty,
            'unit_cost' => 0
        ]);

        $insufficientQty = $qty > ($prevQty + $stockQty);
        if ($insufficientQty) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Insufficient stock');
        }

        $this->prodOrderService->editMaterials($step, $this->rawMaterial->id, $qty);

        if ($insufficientQty) {
            $this->assertDatabaseHas('prod_order_step_products', [
                'id' => $actualItem->id,
                'quantity' => $prevQty,
                'max_quantity' => $prevQty,
                'type' => StepProductType::Actual
            ]);
            $this->assertDatabaseHas('inventory_items', [
                'id' => $inventoryItem->id,
                'quantity' => $stockQty,
            ]);
            $this->assertDatabaseHas('mini_inventories', [
                'id' => $miniStock->id,
                'quantity' => $prevQty,
            ]);
        } else {
            $this->assertDatabaseHas('prod_order_step_products', [
                'id' => $actualItem->id,
                'quantity' => $qty,
                'max_quantity' => $qty,
                'type' => StepProductType::Actual
            ]);
            $this->assertDatabaseHas('inventory_items', [
                'id' => $inventoryItem->id,
                'quantity' => match ($qty) {
                    1, 6, 15 => $stockQty,
                    10 => $stockQty - (10 - 6),
                },
            ]);
            $this->assertDatabaseHas('mini_inventories', [
                'id' => $miniStock->id,
                'quantity' => match ($qty) {
                    1, 6, 15 => $prevQty,
                    10 => 10,
                },
            ]);
        }
    }

    public static function lessMoreQtyProvider(): array
    {
        return [
            'less' => [1],
            'equal' => [6],
            'more' => [10],
            'more than 10' => [15],
        ];
    }
}
