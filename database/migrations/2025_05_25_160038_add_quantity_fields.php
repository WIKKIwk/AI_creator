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
        Schema::table('prod_order_step_products', function (Blueprint $table) {
            $table->double('required_quantity')->nullable();
            $table->double('available_quantity')->nullable();
            $table->double('used_quantity')->nullable();

            $table->dropColumn(['quantity', 'max_quantity']);
            $table->dropColumn('type');
        });

        Schema::table('prod_template_step_products', function (Blueprint $table) {
            $table->dropColumn(['type']);
            $table->renameColumn('quantity', 'required_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_order_step_products', function (Blueprint $table) {
            $table->dropColumn(['required_quantity', 'available_quantity', 'used_quantity']);
            $table->double('quantity')->nullable();
            $table->double('max_quantity')->nullable();
            $table->integer('type');
        });

        Schema::table('prod_template_step_products', function (Blueprint $table) {
            $table->integer('type');
            $table->renameColumn('required_quantity', 'quantity');
        });
    }
};
