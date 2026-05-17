<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractor_time_entries', function (Blueprint $table) {
            $table->foreignUuid('client_task_id')
                ->nullable()
                ->after('task')
                ->constrained('client_tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contractor_time_entries', function (Blueprint $table) {
            $table->dropForeign(['client_task_id']);
            $table->dropColumn('client_task_id');
        });
    }
};
