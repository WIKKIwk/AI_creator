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
            $table->double('expected_quantity')->default(0);
            $table->renameColumn('quantity', 'actual_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_order_products', function (Blueprint $table) {
            $table->dropColumn('expected_quantity');
            $table->renameColumn('actual_quantity', 'quantity');
        });
    }
};
