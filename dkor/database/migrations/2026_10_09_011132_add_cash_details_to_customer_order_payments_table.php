<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Détails d'un paiement comptant : arrondi au 5 ¢ près (montant perçu ou remis moins le montant appliqué à la
     * commande), montant reçu du client et monnaie rendue.
     */
    public function up(): void
    {
        Schema::table('customer_order_payments', function (Blueprint $table) {
            $table->decimal('rounding_adjustment', 12, 2)->nullable()->after('amount');
            $table->decimal('cash_tendered', 12, 2)->nullable()->after('rounding_adjustment');
            $table->decimal('change_given', 12, 2)->nullable()->after('cash_tendered');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_payments', function (Blueprint $table) {
            $table->dropColumn(['rounding_adjustment', 'cash_tendered', 'change_given']);
        });
    }
};
