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
        Schema::table('replicon_projects', function (Blueprint $table) {
            $table->boolean('user_disabled')->default(false);
        });

        Schema::table('replicon_tasks', function (Blueprint $table) {
            $table->boolean('user_disabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('replicon_projects', function (Blueprint $table) {
            $table->dropColumn('user_disabled');
        });

        Schema::table('replicon_tasks', function (Blueprint $table) {
            $table->dropColumn('user_disabled');
        });
    }
};
