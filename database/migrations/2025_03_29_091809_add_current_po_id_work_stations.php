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
            $table->bigInteger('prod_order_id')->nullable();
            $table->foreign('prod_order_id')->references('id')->on('prod_orders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_stations', function (Blueprint $table) {
            $table->dropForeign(['prod_order_id']);
            $table->dropColumn('prod_order_id');
        });
    }
};
