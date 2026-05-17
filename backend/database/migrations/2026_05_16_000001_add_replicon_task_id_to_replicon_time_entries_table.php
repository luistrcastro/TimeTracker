<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replicon_time_entries', function (Blueprint $table) {
            $table->foreignUuid('replicon_task_id')
                ->nullable()
                ->after('sub_project')
                ->constrained('replicon_tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('replicon_time_entries', function (Blueprint $table) {
            $table->dropForeign(['replicon_task_id']);
            $table->dropColumn('replicon_task_id');
        });
    }
};
