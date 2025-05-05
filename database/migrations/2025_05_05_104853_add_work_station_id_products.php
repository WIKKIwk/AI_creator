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
        Schema::table('work_stations', function (Blueprint $table) {
            $table->dropForeign(['output_product_id']);
            $table->dropColumn('output_product_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->bigInteger('work_station_id')->nullable();
            $table->foreign('work_station_id')->references('id')->on('work_stations')->onDelete('set null');

            $table->bigInteger('ready_product_id')->nullable();
            $table->foreign('ready_product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_stations', function (Blueprint $table) {
            $table->bigInteger('output_product_id')->nullable();
            $table->foreign('output_product_id')->references('id')->on('products')->onDelete('set null');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['work_station_id']);
            $table->dropColumn('work_station_id');

            $table->dropForeign(['ready_product_id']);
            $table->dropColumn('ready_product_id');
        });
    }
};
