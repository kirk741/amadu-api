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
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type')->default('psychologist');
            $table->foreignUlid('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('psychologist_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'psychologist_id']);
            $table->unique(['client_id', 'psychologist_id'], 'unique_conversation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
