<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Warehouse;
use App\Enums\MeasureUnit;
use App\Enums\ProductType;
use App\Models\Organization;
use Illuminate\Console\Command;
use App\Models\ProductCategory;
use App\Services\TransactionService;
use Illuminate\Support\Facades\DB;

class InsertProducts extends Command
{
    protected $signature = 'app:generate-products';
    protected $description = 'Generate products for the application';

    public function handle(): void
    {
        /** @var ?Organization $firstOrg */
        $firstOrg = Organization::query()->orderBy('created_at')->first();
        if (!$firstOrg) {
            $this->error('No organization found. Please create an organization first.');
            return;
        }

        /** @var ?Warehouse $firstWarehouse */
        $firstWarehouse = Warehouse::query()->withoutGlobalScopes()->orderBy('created_at')->first();
        if (!$firstWarehouse) {
            $this->error('No warehouse found. Please create a warehouse first.');
            return;
        }

        try {
            $productsData = json_decode(file_get_contents(__DIR__ . '/../Templates/products.json'), true);

            DB::beginTransaction();
            foreach ($productsData as $productItem) {

                /** @var ProductCategory $category */
                $category = ProductCategory::query()->withoutGlobalScopes()->firstOrCreate(
                    [
                        'code' => $productItem['category_short_code'],
                        'organization_id' => $firstOrg->id,
                    ],
                    [
                        'name' => $productItem['category'],
                        'measure_unit' => match ($productItem['measure_unit']) {
                            'Kg', 'KG' => MeasureUnit::KG->value,
                            'Pcs' => MeasureUnit::PCS->value,
                            default => null,
                        },
                        'description' => $productItem['description'],
                    ]
                );

                if (!$category) {
                    $this->error("Failed to create category: {$productItem['category']}");
                    continue;
                }

                /** @var Product $product */
                $product = Product::query()->withoutGlobalScopes()->firstOrCreate(
                    ['code' => $productItem['product_short_code']],
                    [
                        'name' => $productItem['product_name'],
                        'description' => $productItem['description'],
                        'product_category_id' => $category->id,
                        'type' => match ($productItem['product_type']) {
                            'RP' => ProductType::ReadyProduct->value,
                            'RM' => ProductType::RawMaterial->value,
                            'SFP' => ProductType::SemiFinishedProduct->value,
                            default => null,
                        },
                    ]
                );

                $quantity = $productItem['quantity'] ?? 0;

                /** @var TransactionService $transactionService */
                $transactionService = app(TransactionService::class);
                $transactionService->addStock(
                    $product->id,
                    $quantity,
                    $firstWarehouse->id,
                    withTransaction: false
                );
            }

            $this->info('Products generated successfully.');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
        }
    }
}
