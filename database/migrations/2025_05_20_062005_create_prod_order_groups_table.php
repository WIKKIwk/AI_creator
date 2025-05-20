<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prod_order_groups', function(Blueprint $table) {
            $table->id();
            $table->integer('type')->comment('ByOrder, ByCatalog');
            $table->foreignId('organization_id')->nullable()->constrained('organizations');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('deadline')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('prod_orders', function(Blueprint $table) {
            $table->dropForeign(['agent_organization_id']);
            $table->dropColumn('agent_organization_id');
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');

            $table->foreignId('group_id')->constrained('prod_order_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_orders', function(Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');

            $table->foreignId('agent_organization_id')->constrained('organizations');
            $table->foreignId('warehouse_id')->constrained('warehouses');
        });

        Schema::dropIfExists('prod_order_groups');
    }
};
