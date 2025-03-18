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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products');
            $table->double('quantity');
            $table->bigInteger('work_station_id')->nullable();
            $table->foreign('work_station_id')->references('id')->on('work_stations');
            $table->bigInteger('storage_location_id')->nullable();
            $table->foreign('storage_location_id')->references('id')->on('storage_locations');
            $table->bigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
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
