<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rattache l'employé à un magasin (paramètres de vacances et de maladie) et généralise
     * les heures par jour de vacances en heures par jour (vacances et maladie payée).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->renameColumn('vacation_hours_per_day', 'hours_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('hours_per_day', 'vacation_hours_per_day');
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
