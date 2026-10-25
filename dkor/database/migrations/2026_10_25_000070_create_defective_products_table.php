<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defective_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->string('resolution');
            $table->string('status')->default('open');
            $table->text('reason')->nullable();
            $table->string('replacement_part')->nullable();
            $table->foreignId('supplier_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreignId('reception_line_id')->nullable()->constrained()->nullOnDelete();

            $table->index(['status', 'resolution']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defective_products');
    }
};
