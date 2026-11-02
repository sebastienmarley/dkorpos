<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taux de change figé à la saisie (ADR 0016) : les montants de la facture restent dans la devise du fournisseur, et
     * son écriture est convertie en CAD à ce taux. Une facture sans réception ni commande choisit son compte de
     * charge. Les factures existantes étaient toutes en dollars canadiens.
     */
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->default('1')->after('invoice_total');
            $table->foreignId('ledger_account_id')->nullable()->after('supplier_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_account_id');
            $table->dropColumn('exchange_rate');
        });
    }
};
