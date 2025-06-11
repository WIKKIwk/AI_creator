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
        Schema::table('prod_order_step_executions', function (Blueprint $table) {
            $table->foreignId('approved_by_prod_manager_id')->nullable()->constrained('users');
            $table->timestamp('approved_at_prod_manager_id')->nullable();

            $table->foreignId('approved_by_prod_senior_manager_id')->nullable()->constrained('users');
            $table->timestamp('approved_at_prod_senior_manager_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_order_step_executions', function (Blueprint $table) {
            //
        });
    }
};
