<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index des filtres du journal d'inventaire : chaque filtre (magasin, type, état de départ ou d'arrivée) est suivi de
 * created_at, la période et le tri du journal. PostgreSQL combine les deux index d'état pour le filtre « de ou vers ».
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = ['store_id', 'type', 'from_status', 'to_status'];

    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index('created_at');

            foreach (self::COLUMNS as $column) {
                $table->index([$column, 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['created_at']);

            foreach (self::COLUMNS as $column) {
                $table->dropIndex([$column, 'created_at']);
            }
        });
    }
};
