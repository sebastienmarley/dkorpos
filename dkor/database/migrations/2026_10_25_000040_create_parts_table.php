<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->string('clean_model');
            $table->string('description');
            $table->integer('last_cost')->default(0);
            $table->timestamps();

            $table->unique(['supplier_id', 'clean_model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
