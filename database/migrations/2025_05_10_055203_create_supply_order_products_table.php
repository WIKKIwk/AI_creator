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
        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            $table->dropColumn('quantity');

            $table->foreignId('product_category_id')->nullable()->constrained('product_categories');
        });

        Schema::create('supply_order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_order_id')->constrained('supply_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->double('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_order_products');

        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropForeign(['product_category_id']);
            $table->dropColumn('product_category_id');

            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products');
            $table->double('quantity');
        });
    }
};
