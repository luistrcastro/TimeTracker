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
        Schema::create('contractor_time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('task')->default('');
            $table->string('description')->default('');
            $table->string('sub_description')->default('');
            $table->date('date');
            $table->time('start')->nullable();
            $table->time('finish')->nullable();
            $table->smallInteger('duration_minutes')->default(0);
            $table->boolean('invoiced')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'date']);
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractor_time_entries');
    }
};
