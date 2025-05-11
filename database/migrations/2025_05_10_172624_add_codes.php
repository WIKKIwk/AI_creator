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
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('code', 15)->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 15)->nullable();
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code', 15)->nullable();
        });
        Schema::table('agents', function (Blueprint $table) {
            $table->string('code', 15)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('code');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('code');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('code');
        });
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
