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
        Schema::table('work_stations', function(Blueprint $table) {
            $table->bigInteger('output_product_id')->nullable();
            $table->foreign('output_product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_stations', function(Blueprint $table) {
            $table->dropForeign(['output_product_id']);
            $table->dropColumn('output_product_id');
        });
    }
};
