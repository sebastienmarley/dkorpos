<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->integer('price');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_clearances');
    }
};
