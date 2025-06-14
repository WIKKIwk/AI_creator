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
            $table->renameColumn('approved_at_prod_manager_id', 'approved_at_prod_manager');
            $table->renameColumn('approved_by_prod_manager_id', 'approved_by_prod_manager');

            $table->renameColumn('approved_at_prod_senior_manager_id', 'approved_at_prod_senior_manager');
            $table->renameColumn('approved_by_prod_senior_manager_id', 'approved_by_prod_senior_manager');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_order_step_executions', function (Blueprint $table) {
            $table->renameColumn('approved_at_prod_manager', 'approved_at_prod_manager_id');
            $table->renameColumn('approved_by_prod_manager', 'approved_by_prod_manager_id');

            $table->renameColumn('approved_at_prod_senior_manager', 'approved_at_prod_senior_manager_id');
            $table->renameColumn('approved_by_prod_senior_manager', 'approved_by_prod_senior_manager_id');
        });
    }
};
