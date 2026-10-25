<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->date('counted_on');
            $table->string('status')->default('in_progress');
            $table->timestamp('stock_frozen_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();

            $table->index('status');
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_counts');
    }
};
