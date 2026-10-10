<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produits qu'une pièce répare (ex. : les verres d'une même famille de lampes).
     */
    public function up(): void
    {
        Schema::create('part_product', function (Blueprint $table) {
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['part_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_product');
    }
};
