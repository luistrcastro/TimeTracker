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
        Schema::create('replicon_time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('project')->default('');
            $table->string('sub_project')->default('');
            $table->string('description')->default('');
            $table->string('sub_description')->default('');
            $table->string('further_info')->default('');
            $table->time('start')->nullable();
            $table->time('finish')->nullable();
            $table->smallInteger('duration_minutes')->default(0);
            $table->boolean('logged')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replicon_time_entries');
    }
};
