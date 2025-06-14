<?php

use App\Enums\PartnerType;
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
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn('agent_id');
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });

        Schema::dropIfExists('agent_products');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('suppliers');

        Schema::create('organization_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('partner_id')->constrained('organizations');
            $table->integer('type')->default(PartnerType::Agent->value);
            $table->timestamps();
        });

        Schema::create('organization_partner_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_partner_id')->constrained('organization_partners');
            $table->foreignId('product_id')->constrained('products');
            $table->double('price');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_partner_products');
        Schema::dropIfExists('organization_partners');

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->integer('type')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
    }
};
