<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_order_delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('customer_order_delivery_id');
            $table->foreignId('customer_order_line_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->timestamps();
            $table->string('loaded_from_status')->nullable();
            $table->integer('accepted_quantity')->nullable();
            $table->string('refusal_reason')->nullable();

            $table->unique(['customer_order_delivery_id', 'customer_order_line_id'], 'delivery_line_unique');
            $table->foreign('customer_order_delivery_id')->references('id')->on('customer_order_deliveries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_delivery_lines');
    }
};
