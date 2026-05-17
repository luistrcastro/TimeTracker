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
        Schema::create('replicon_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('replicon_project_id')->constrained('replicon_projects')->cascadeOnDelete();
            $table->string('replicon_task_id');
            $table->string('name')->default('');
            $table->jsonb('path')->default('[]');
            $table->timestamps();
            $table->unique(['replicon_project_id', 'replicon_task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replicon_tasks');
    }
};
