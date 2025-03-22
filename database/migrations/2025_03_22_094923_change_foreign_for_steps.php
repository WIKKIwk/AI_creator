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
        Schema::table('prod_template_steps', function (Blueprint $table) {
            $table->dropForeign(['prod_template_id']);
            $table->foreign('prod_template_id')->references('id')->on('prod_templates')->onDelete('cascade');
        });

        Schema::table('prod_template_step_products', function (Blueprint $table) {
            $table->dropForeign(['prod_template_step_id']);
            $table->foreign('prod_template_step_id')->references('id')->on('prod_template_steps')->onDelete('cascade');
        });

        Schema::table('prod_order_steps', function (Blueprint $table) {
            $table->dropForeign(['prod_order_id']);
            $table->foreign('prod_order_id')->references('id')->on('prod_orders')->onDelete('cascade');
        });

        Schema::table('prod_order_step_products', function (Blueprint $table) {
            $table->dropForeign(['prod_order_step_id']);
            $table->foreign('prod_order_step_id')->references('id')->on('prod_order_steps')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
