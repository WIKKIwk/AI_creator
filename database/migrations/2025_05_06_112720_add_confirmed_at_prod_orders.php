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
            $table->timestamp('confirmed_at')->nullable()->after('status');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
        });

        Schema::table('supply_orders', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('status');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn('confirmed_at');
            $table->dropColumn('confirmed_by');
        });

        Schema::table('prod_orders', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn('confirmed_at');
            $table->dropColumn('confirmed_by');
        });
    }
};
