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
            $table->decimal('cost', 10, 2);
            $table->date('inserted_at');
            $table->date('delivered_at')->nullable();
            $table->timestamps();

            // FIFO ordering will use inserted_at
            $table->index(['product_id', 'inserted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
    }
};
