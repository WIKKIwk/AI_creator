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
            $table->timestamp('declined_at')->nullable()->after('completed_at');
            $table->foreignId('declined_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_order_step_executions', function (Blueprint $table) {
            $table->dropForeign(['declined_by']);
            $table->dropColumn(['declined_at', 'declined_by']);
        });
    }
};
