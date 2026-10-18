<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cycle de vie des unités d'inventaire (ADR 0011) : le mouvement qui a sorti une unité (pour rouvrir celles de la
     * même commande à un retour) et la valeur au coût FIFO des mouvements qui créent ou sortent des unités.
     */
    public function up(): void
    {
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->foreignId('exit_movement_id')->nullable()->after('delivered_at')->constrained('inventory_movements')->nullOnDelete();
            $table->index('exit_movement_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->integer('value')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn('value');
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropIndex(['exit_movement_id']);
            $table->dropConstrainedForeignId('exit_movement_id');
        });
    }
};
