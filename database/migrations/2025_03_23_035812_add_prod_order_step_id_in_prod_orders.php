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
        Schema::table('prod_orders', function (Blueprint $table) {
            $table->bigInteger('current_step_id')->nullable();
            $table->foreign('current_step_id')->references('id')->on('prod_order_steps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_orders', function (Blueprint $table) {
            $table->dropForeign(['current_step_id']);
            $table->dropColumn('current_step_id');
        });
    }
};
