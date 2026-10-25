<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();

            $table->unique(['store_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_zones');
    }
};
