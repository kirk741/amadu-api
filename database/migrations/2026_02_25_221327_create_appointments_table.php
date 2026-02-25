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
            $table->ulid('id')->primary();
            $table->datetime('scheduled_at');
            $table->integer('duration_minutes')->default(60);
            $table->string('status')->default('scheduled');
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->cascadeOnDelete();
            $table->foreignUlid('psychologist_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('client_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['psychologist_id', 'scheduled_at']);
            $table->index(['client_id', 'scheduled_at']);
            $table->index('status');
            $table->index('scheduled_at');
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
