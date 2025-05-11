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
            $table->integer('state')->nullable()->change();
        });

        Schema::create('supply_order_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_order_id')->constrained('supply_orders')->onDelete('cascade');
            $table->integer('state')->default(SupplyOrderState::Created->value);
            $table->string('status')->nullable();

            $table->foreignId('created_by')->constrained('users')->onDelete('set null');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_order_steps');

        Schema::table('supply_orders', function (Blueprint $table) {
            $table->integer('state')->default(SupplyOrderState::Created->value)->change();
        });
    }
};
