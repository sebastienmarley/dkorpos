<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pièces de remplacement commandées pour les clients (non inventoriées). Une pièce est unique chez son
     * fournisseur par son modèle nettoyé (même normalisation que les produits).
     */
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->string('clean_model');
            $table->string('description');
            $table->decimal('last_cost', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['supplier_id', 'clean_model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
