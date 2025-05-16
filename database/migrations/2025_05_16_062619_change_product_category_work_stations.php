<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_station_categories', function(Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_code')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->timestamps();
        });

        Schema::table('work_stations', function(Blueprint $table) {
            $table->dropForeign(['product_category_id']);
            $table->dropColumn('product_category_id');
            $table->foreignId('work_station_category_id')->nullable()->constrained('work_station_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_stations', function(Blueprint $table) {
            $table->dropForeign(['work_station_category_id']);
            $table->dropColumn('work_station_category_id');
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories');
        });

        Schema::dropIfExists('work_station_categories');
    }
};
