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
        Schema::disableForeignKeyConstraints();

        Schema::table('prod_orders', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn('agent_id');

            $table->foreignId('agent_organization_id')->constrained('organizations');
        });

        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');

            $table->foreignId('supplier_organization_id')->constrained('organizations');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('prod_orders', function (Blueprint $table) {
            $table->dropForeign(['agent_organization_id']);
            $table->dropColumn('agent_organization_id');

            $table->foreignId('agent_id')->constrained('agents');
        });

        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_organization_id']);
            $table->dropColumn('supplier_organization_id');

            $table->foreignId('supplier_id')->constrained('suppliers');
        });

        Schema::enableForeignKeyConstraints();
    }
};
