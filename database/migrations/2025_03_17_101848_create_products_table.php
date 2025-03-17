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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('product_categories');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->integer('type');
            $table->string('name');
            $table->string('description')->nullable();
            $table->double('price');
            $table->integer('measure_unit');
            $table->bigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('product_categories');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
