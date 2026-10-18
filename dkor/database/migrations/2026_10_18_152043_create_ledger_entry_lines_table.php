<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lignes d'une écriture : un débit ou un crédit, en cents (ADR 0008), sur un compte.
     */
    public function up(): void
    {
        Schema::create('ledger_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->integer('debit')->default(0);
            $table->integer('credit')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entry_lines');
    }
};
