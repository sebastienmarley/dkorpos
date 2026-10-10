<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('province', 2);
            $table->string('name');
            $table->decimal('rate', 6, 3);
            $table->boolean('is_compound')->default(false);
            $table->date('start_date');
            $table->date('end_date')->default('2100-12-31');
            $table->timestamps();

            $table->index(['province', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
