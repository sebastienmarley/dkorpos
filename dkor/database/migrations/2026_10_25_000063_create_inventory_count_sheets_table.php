<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('inventory_zone_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('pass');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state')->default('todo');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->index('assigned_to');
            $table->index(['inventory_count_id', 'status', 'inventory_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_sheets');
    }
};
