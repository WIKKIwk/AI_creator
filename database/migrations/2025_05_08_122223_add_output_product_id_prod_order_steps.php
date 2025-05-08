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
        Schema::table('prod_order_steps', function (Blueprint $table) {
            $table->bigInteger('output_product_id')->nullable();
            $table->foreign('output_product_id')->references('id')->on('products')->onDelete('set null');
            $table->double('expected_quantity')->nullable();
            $table->double('output_quantity')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_order_steps', function (Blueprint $table) {
            $table->dropForeign(['output_product_id']);
            $table->dropColumn('output_product_id');
            $table->dropColumn('expected_quantity');
            $table->dropColumn('output_quantity');
        });
    }
};
