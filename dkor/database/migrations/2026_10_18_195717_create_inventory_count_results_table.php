<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quantités retenues d'une portée réconciliée (comptes A et B concordants, recompte ou décision du superviseur).
     * Elles alimentent les lignes de la prise.
     */
    public function up(): void
    {
        Schema::create('inventory_count_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('inventory_zone_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->string('source');
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_count_id', 'status', 'inventory_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_results');
    }
};
