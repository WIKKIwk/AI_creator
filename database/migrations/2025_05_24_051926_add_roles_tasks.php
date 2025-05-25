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
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['to_user_id']);
            $table->dropColumn('to_user_id');
            $table->dropColumn('to_user_role');

            $table->string('to_user_ids')->nullable()->after('from_user_id');
            $table->string('to_user_roles')->nullable()->after('to_user_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('to_user_ids');
            $table->dropColumn('to_user_roles');

            $table->foreignId('to_user_id')->nullable()->after('from_user_id')->constrained('users', 'id')->nullOnDelete();
            $table->string('to_user_role')->nullable()->after('to_user_id');
        });
    }
};
