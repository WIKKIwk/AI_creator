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
            $table->double('performance_qty')->nullable();
            $table->integer('performance_unit')->nullable();
            $table->integer('performance_duration')->nullable();
            $table->integer('performance_duration_unit')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_stations', function (Blueprint $table) {
            $table->dropColumn('performance_qty');
            $table->dropColumn('performance_unit');
            $table->dropColumn('performance_duration');
            $table->dropColumn('performance_duration_unit');
        });
    }
};
