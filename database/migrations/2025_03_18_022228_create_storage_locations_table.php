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
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('number')->nullable();
            $table->string('floor')->nullable();
            $table->string('description')->nullable();
            $table->bigInteger('work_station_id')->unsigned();
            $table->foreign('work_station_id')->references('id')->on('work_stations');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_locations');
    }
};
