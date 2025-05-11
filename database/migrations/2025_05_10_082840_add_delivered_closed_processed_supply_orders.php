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
        Schema::table('supply_orders', function (Blueprint $table) {
            $table->timestamp('progressed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropColumn('processed_by');
            $table->dropColumn('progressed_at');
            $table->dropForeign(['delivered_by']);
            $table->dropColumn('delivered_by');
            $table->dropColumn('delivered_at');
            $table->dropForeign(['closed_by']);
            $table->dropColumn('closed_by');
            $table->dropColumn('closed_at');
        });
    }
};
