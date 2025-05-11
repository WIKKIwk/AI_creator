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
            $table->string('number', 50)->nullable()->after('id');
        });
        Schema::table('prod_orders', function (Blueprint $table) {
            $table->string('number', 50)->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropColumn('number');
        });
        Schema::table('prod_orders', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
