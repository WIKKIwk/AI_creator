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
        Schema::create('performance_rates', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('work_station_id')->unsigned();
            $table->foreign('work_station_id')->references('id')->on('work_stations');
            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products');
            $table->double('quantity');
            $table->integer('duration');
            $table->integer('duration_unit')->comment('1: Year, 2: Month, 3: Week, 4: Day, 5: Hour');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};
