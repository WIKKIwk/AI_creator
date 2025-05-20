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
        Schema::table('prod_orders', function (Blueprint $table) {
            $table->unique(['product_id', 'group_id'], 'prod_orders_product_id_group_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_orders', function (Blueprint $table) {
            $table->dropUnique('prod_orders_product_id_group_id_unique');
        });
    }
};
