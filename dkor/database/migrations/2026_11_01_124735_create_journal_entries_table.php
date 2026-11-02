<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Écritures manuelles du journal général (ADR 0016) : soldes d'ouverture, dépôts des processeurs de paiement,
     * virements, frais bancaires, paie. Chacune passe une écriture au grand livre qui la référence; une correction
     * passe un renversement (`reverses_id`).
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('description');
            $table->string('document_number', 100)->nullable();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
