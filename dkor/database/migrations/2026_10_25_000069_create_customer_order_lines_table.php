<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->text('note')->nullable();
            $table->string('status');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_on_order')->default(0);
            $table->foreignId('supplier_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_order_pickup_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('cancellation_fee')->nullable();
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->integer('unit_cost')->nullable();
            $table->string('quote_number')->nullable();
            $table->foreignId('part_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_no_charge')->default(false);
            $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_lines');
    }
};
