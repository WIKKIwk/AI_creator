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
        Schema::create('work_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('warehouse_id')->unsigned();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->bigInteger('product_category_id')->nullable();
            $table->foreign('product_category_id')->references('id')->on('product_categories');
            $table->integer('type')->nullable();
            $table->timestamps();
        });

        /*Schema::create('work_station_tools', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('work_station_id')->unsigned();
            $table->foreign('work_station_id')->references('id')->on('work_stations');
            $table->bigInteger('tool_id')->unsigned();
            $table->foreign('tool_id')->references('id')->on('tools');
            $table->timestamps();
        });*/
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_stations');
    }
};
