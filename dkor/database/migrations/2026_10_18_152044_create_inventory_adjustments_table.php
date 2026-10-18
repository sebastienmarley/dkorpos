<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajustements d'inventaire (issue #52, ADR 0011) : une entrée ou une sortie d'inventaire sans document d'achat
     * ni de vente. Son mouvement et son écriture la référencent; un ajustement ne se modifie ni ne se supprime.
     */
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('reason');
            $table->string('status');
            $table->boolean('is_entry');
            $table->integer('quantity');
            $table->string('note');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
