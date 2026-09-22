<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('replicon_projects', function (Blueprint $table) {
            $table->boolean('active_override')->nullable()->default(null)->after('user_disabled');
        });
        Schema::table('replicon_tasks', function (Blueprint $table) {
            $table->boolean('active_override')->nullable()->default(null)->after('user_disabled');
        });

        DB::table('replicon_projects')->where('user_disabled', true)->update(['active_override' => false]);
        DB::table('replicon_tasks')->where('user_disabled', true)->update(['active_override' => false]);

        Schema::table('replicon_projects', function (Blueprint $table) {
            $table->dropColumn('user_disabled');
        });
        Schema::table('replicon_tasks', function (Blueprint $table) {
            $table->dropColumn('user_disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('replicon_projects', function (Blueprint $table) {
            $table->boolean('user_disabled')->default(false)->after('is_active');
        });
        Schema::table('replicon_tasks', function (Blueprint $table) {
            $table->boolean('user_disabled')->default(false)->after('is_active');
        });

        DB::table('replicon_projects')->where('active_override', false)->update(['user_disabled' => true]);
        DB::table('replicon_tasks')->where('active_override', false)->update(['user_disabled' => true]);

        Schema::table('replicon_projects', function (Blueprint $table) {
            $table->dropColumn('active_override');
        });
        Schema::table('replicon_tasks', function (Blueprint $table) {
            $table->dropColumn('active_override');
        });
    }
};
