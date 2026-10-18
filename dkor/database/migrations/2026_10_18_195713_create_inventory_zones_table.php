<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zones permanentes de l'entrepôt pour le comptage du stock (ADR 0011) : réutilisées d'une prise à l'autre.
     */
    public function up(): void
    {
        Schema::create('inventory_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_zones');
    }
};
