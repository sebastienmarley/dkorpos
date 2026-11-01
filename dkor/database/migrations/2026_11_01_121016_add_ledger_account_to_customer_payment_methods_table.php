<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compte d'encaisse ou de compensation où passent les paiements reçus par ce mode (ADR 0016); vide : Encaisse CAD.
     */
    public function up(): void
    {
        Schema::table('customer_payment_methods', function (Blueprint $table) {
            $table->foreignId('ledger_account_id')->nullable()->after('is_usable_on_delivery')->constrained('ledger_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_payment_methods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_account_id');
        });
    }
};
