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
        Schema::table('work_stations', function (Blueprint $table) {
            $table->foreignId('prod_manager_id')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_stations', function (Blueprint $table) {
            $table->dropForeign(['prod_manager_id']);
            $table->dropColumn('prod_manager_id');
        });
    }
};
