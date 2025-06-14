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
        Schema::table('prod_order_groups', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->foreignId('agent_id')->nullable()->constrained('organization_partners');
        });

        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_organization_id']);
            $table->dropColumn('supplier_organization_id');
            $table->foreignId('supplier_id')->nullable()->constrained('organization_partners');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_order_groups', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn('agent_id');
            $table->foreignId('organization_id')->nullable()->constrained('organizations');
        });

        Schema::table('supply_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
            $table->foreignId('supplier_organization_id')->nullable()->constrained('organizations');
        });
    }
};
