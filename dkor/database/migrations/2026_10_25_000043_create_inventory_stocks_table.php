<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_in_stock')->default(0);
            $table->integer('quantity_on_order')->default(0);
            $table->integer('quantity_in_demo')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_customer_order')->default(0);
            $table->timestamps();
            $table->integer('quantity_in_delivery')->default(0);
            $table->integer('quantity_defective_stock')->default(0);
            $table->integer('quantity_defective_shipped')->default(0);
            $table->integer('quantity_lost')->default(0);
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('quantity_in_transfer')->default(0);
            $table->integer('quantity_in_transit')->default(0);
            $table->integer('quantity_customer_in_transfer')->default(0);
            $table->integer('quantity_customer_in_transit')->default(0);

            $table->unique(['product_id', 'store_id']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
