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
        Schema::table('prod_order_step_products', function (Blueprint $table) {
            $table->integer('max_quantity')->nullable()->after('quantity')->comment('Maximum quantity allowed for this product in the order step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_order_step_products', function (Blueprint $table) {
            $table->dropColumn('max_quantity');
        });
    }
};
