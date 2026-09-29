<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week_template_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('week_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->timestamps();

            $table->unique(['week_template_id', 'user_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week_template_entries');
    }
};
