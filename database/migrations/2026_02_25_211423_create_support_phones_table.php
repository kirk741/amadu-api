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
        Schema::create('support_phones', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('title');
            $table->string('description');
            $table->timestamps();

            $table->index('phone');
            $table->index('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_phones');
    }
};
