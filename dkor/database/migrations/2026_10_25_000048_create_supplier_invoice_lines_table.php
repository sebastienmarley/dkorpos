<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reception_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->integer('unit_cost');
            $table->timestamps();
            $table->foreignId('supplier_order_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('quantity_short')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_lines');
    }
};
