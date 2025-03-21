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
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->renameColumn('floor', 'floors_count');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->integer('storage_floor')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            //
        });
    }
};
