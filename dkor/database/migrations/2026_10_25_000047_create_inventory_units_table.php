<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('cost');
            $table->date('inserted_at');
            $table->date('delivered_at')->nullable();
            $table->timestamps();
            $table->foreignId('reception_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exit_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();

            $table->index('exit_movement_id');
            $table->index(['product_id', 'inserted_at']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
    }
};
