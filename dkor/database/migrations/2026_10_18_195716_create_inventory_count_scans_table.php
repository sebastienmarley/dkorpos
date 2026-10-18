<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal des lectures d'une feuille de compte : le code lu (vide pour une correction), la quantité ajoutée, le
     * produit (vide tant qu'un code inconnu n'est pas associé) et l'annulation éventuelle.
     */
    public function up(): void
    {
        Schema::create('inventory_count_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_sheet_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('undone_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_scans');
    }
};
