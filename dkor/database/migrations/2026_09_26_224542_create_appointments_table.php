<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('hour'); // 8 à 18
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
