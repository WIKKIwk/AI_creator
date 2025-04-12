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
            $table->double('actual_cost')->nullable()->after('total_cost');
            $table->double('actual_deadline')->nullable()->after('deadline');

            $table->timestamp('started_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users');

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_orders', function (Blueprint $table) {
            $table->dropForeign(['started_by']);
            $table->dropForeign(['approved_by']);

            $table->dropColumn('actual_cost');
            $table->dropColumn('actual_deadline');

            $table->dropColumn('started_at');
            $table->dropColumn('started_by');

            $table->dropColumn('approved_at');
            $table->dropColumn('approved_by');
        });
    }
};
