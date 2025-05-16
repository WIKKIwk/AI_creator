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
        Schema::table('prod_template_steps', function(Blueprint $table) {
            $table->string('measure_unit')->default(MeasureUnit::KG->value)->after('output_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_template_steps', function(Blueprint $table) {
            $table->dropColumn('measure_unit');
        });
    }
};
