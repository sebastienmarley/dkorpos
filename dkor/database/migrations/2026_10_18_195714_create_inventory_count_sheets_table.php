<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feuilles de compte d'une prise d'inventaire : une portée (état compté, et zone pour le stock), une passe (A, B ou
     * recompte), le compteur attribué et la réconciliation de la portée.
     */
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
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['inventory_count_id', 'status', 'inventory_zone_id']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_sheets');
    }
};
