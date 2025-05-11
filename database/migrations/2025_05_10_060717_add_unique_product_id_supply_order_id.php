<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supply_order_products', function (Blueprint $table) {
            $table->unique(['product_id', 'supply_order_id'], 'unique_product_supply_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_order_products', function (Blueprint $table) {
            $table->dropUnique('unique_product_supply_order');
        });
    }
};
