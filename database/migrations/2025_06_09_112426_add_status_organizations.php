<?php

use App\Enums\OrganizationStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function(Blueprint $table) {
            $table->integer('status')->default(OrganizationStatus::Active->value);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function(Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
