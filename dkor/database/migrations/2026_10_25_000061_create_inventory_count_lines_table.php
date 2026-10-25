<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->integer('counted_quantity')->nullable();
            $table->boolean('is_kept')->default(false);
            $table->integer('expected_quantity')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->foreignId('inventory_adjustment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['inventory_count_id', 'product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_lines');
    }
};
