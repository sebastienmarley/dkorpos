<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('to_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->integer('quantity_received')->nullable();
            $table->string('source_status');
            $table->string('status');
            $table->string('refusal_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['from_store_id', 'status']);
            $table->index(['to_store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_transfers');
    }
};
