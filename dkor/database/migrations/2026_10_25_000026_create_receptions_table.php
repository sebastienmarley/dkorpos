<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receptions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('status')->default('completed');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();

            $table->unique('number');
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receptions');
    }
};
