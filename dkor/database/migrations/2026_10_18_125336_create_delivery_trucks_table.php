<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Camions de livraison d'un magasin, chacun avec le nombre de livraisons qu'il peut faire par demi-journée.
     */
    public function up(): void
    {
        Schema::create('delivery_trucks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity_per_period');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_trucks');
    }
};
