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
        Schema::create('prod_order_steps', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('prod_order_id')->unsigned();
            $table->foreign('prod_order_id')->references('id')->on('prod_orders');
            $table->bigInteger('work_station_id')->unsigned();
            $table->foreign('work_station_id')->references('id')->on('work_stations');
            $table->integer('sequence');
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        Schema::create('prod_order_step_products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('prod_order_step_id')->unsigned();
            $table->foreign('prod_order_step_id')->references('id')->on('prod_order_steps');
            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products');
            $table->double('quantity');
            $table->integer('type')->comment('Required, Expected, Produced');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_order_steps');
    }
};
