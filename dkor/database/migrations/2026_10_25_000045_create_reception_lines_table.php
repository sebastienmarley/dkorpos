<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reception_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reception_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->integer('unit_cost');
            $table->timestamps();
            $table->integer('quantity_reversed')->default(0);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason')->nullable();
            $table->integer('quantity_damaged')->default(0);
            $table->integer('quantity_short')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reception_lines');
    }
};
