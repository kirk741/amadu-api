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
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('psychologist_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->integer('duration_minutes');
            $table->enum('status', ['pending', 'cancelled', 'finished'])->default('pending');
            $table->timestamps();

            $table->index('psychologist_id');
            $table->index('client_id');
            $table->index('scheduled_at');
            $table->index('status');
            $table->index(['psychologist_id', 'scheduled_at']);
            $table->index(['client_id', 'scheduled_at']);
            $table->index(['psychologist_id', 'status', 'scheduled_at']);
            $table->index(['client_id', 'status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
