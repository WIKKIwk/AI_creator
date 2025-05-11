<?php

use App\Enums\SupplyOrderState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supply_orders', function (Blueprint $table) {
            $table->integer('state')->default(SupplyOrderState::Created->value);
            $table->string('status')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropColumn('state');
            $table->integer('status')->change();
        });
    }
};
