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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products');
            $table->double('quantity');
            $table->double('unit_cost');
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('inventory_id');
            $table->foreign('inventory_id')->references('id')->on('inventories');
            $table->bigInteger('storage_location_id')->nullable();
            $table->foreign('storage_location_id')->references('id')->on('storage_locations');
            $table->double('quantity');
            $table->timestamps();
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products');
            $table->double('quantity');
            $table->double('unit_cost');
            $table->bigInteger('storage_location_id')->nullable();
            $table->foreign('storage_location_id')->references('id')->on('storage_locations');
            $table->integer('type')->comment('1: Entry, 2: Exit');
            $table->timestamps();
        });

        Schema::create('mini_inventories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('work_station_id');
            $table->foreign('work_station_id')->references('id')->on('work_stations');
            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products');
            $table->double('quantity');
            $table->double('unit_cost');
            $table->integer('status')->comment('Not ready, Ready, Approved, Rejected');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
