<?php

use App\Enums\MeasureUnit;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_categories', function(Blueprint $table) {
            $table->integer('measure_unit')->default(MeasureUnit::KG->value)->after('name');
        });
        Schema::table('products', function(Blueprint $table) {
            $table->dropColumn('measure_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function(Blueprint $table) {
            $table->dropColumn('measure_unit');
        });
        Schema::table('products', function(Blueprint $table) {
            $table->integer('measure_unit');
        });
    }
};
