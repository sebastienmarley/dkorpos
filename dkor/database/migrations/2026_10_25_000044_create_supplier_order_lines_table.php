<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->integer('quantity');
            $table->integer('unit_cost');
            $table->integer('quantity_received')->default(0);
            $table->timestamps();
            $table->string('status')->default('active');
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->bigInteger('substituted_from_line_id')->nullable();
            $table->foreignId('part_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('quantity_short')->default(0);
            $table->boolean('is_overship')->default(false);

            $table->foreign('substituted_from_line_id')->references('id')->on('supplier_order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_lines');
    }
};
