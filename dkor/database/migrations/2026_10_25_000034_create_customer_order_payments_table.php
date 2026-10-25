<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_order_pickup_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('amount');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->string('type')->default('payment');
            $table->integer('rounding_adjustment')->nullable();
            $table->integer('cash_tendered')->nullable();
            $table->integer('change_given')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_payments');
    }
};
