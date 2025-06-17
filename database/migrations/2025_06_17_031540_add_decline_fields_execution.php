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
            $table->timestamp('declined_at_prod_manager')->nullable();
            $table->unsignedBigInteger('declined_by_prod_manager')->nullable();
            $table->text('decline_comment_prod_manager')->nullable();

            $table->timestamp('declined_at_senior_prod_manager')->nullable();
            $table->unsignedBigInteger('declined_by_senior_prod_manager')->nullable();
            $table->text('decline_comment_senior_prod_manager')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_order_step_executions', function (Blueprint $table) {
            $table->dropColumn([
                'declined_at_prod_manager',
                'declined_by_prod_manager',
                'decline_comment_prod_manager',
                'declined_at_senior_prod_manager',
                'declined_by_senior_prod_manager',
                'decline_comment_senior_prod_manager'
            ]);
        });
    }
};
