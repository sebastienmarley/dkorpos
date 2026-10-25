<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('new');
            $table->integer('balance_due')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->integer('subtotal')->default(0);
            $table->integer('total')->default(0);
            $table->integer('amount_paid')->default(0);
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_orders');
    }
};
