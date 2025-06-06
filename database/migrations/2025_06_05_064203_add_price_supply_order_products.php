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
            $table->double('price')->default(0)->after('expected_quantity')->comment('Price of the product in the supply order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_order_products', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
