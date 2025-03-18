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
        Schema::create('stock_processes', function (Blueprint $table) {
            $table->id();
            $table->integer('type')->comment('1: Entry, 2: Exit');
            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products');
            $table->double('quantity')->unsigned();

            $table->bigInteger('work_station_id')->unsigned()->nullable();
            $table->foreign('work_station_id')->references('id')->on('work_stations');

            $table->bigInteger('storage_location_id')->unsigned()->nullable();
            $table->foreign('storage_location_id')->references('id')->on('storage_locations');

            $table->bigInteger('warehouse_id')->unsigned();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');

            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_processes');
    }
};
