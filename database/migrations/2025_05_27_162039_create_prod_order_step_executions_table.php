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
        Schema::create('prod_order_step_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prod_order_step_id')->constrained('prod_order_steps')->onDelete('cascade');
            $table->float('output_quantity');
            $table->string('notes')->nullable();
            $table->foreignId('executed_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('prod_order_step_execution_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prod_order_step_execution_id')->constrained('prod_order_step_executions')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->float('used_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_order_step_executions');
        Schema::dropIfExists('prod_order_step_execution_products');
    }
};
