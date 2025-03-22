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
        Schema::create('prod_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('prod_template_steps', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('prod_template_id')->unsigned();
            $table->foreign('prod_template_id')->references('id')->on('prod_templates');
            $table->bigInteger('work_station_id')->unsigned();
            $table->foreign('work_station_id')->references('id')->on('work_stations');
            $table->integer('sequence');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_template_stations');
        Schema::dropIfExists('prod_templates');
    }
};
