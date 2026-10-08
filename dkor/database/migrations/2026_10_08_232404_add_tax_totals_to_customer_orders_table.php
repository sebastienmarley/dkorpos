<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Totaux de la commande client : sous-total des lignes facturables, TPS, TVQ, total et montant payé
     * (le solde à payer est le total moins le payé).
     */
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0)->after('status');
            $table->decimal('gst', 12, 2)->default(0)->after('subtotal');
            $table->decimal('qst', 12, 2)->default(0)->after('gst');
            $table->decimal('total', 12, 2)->default(0)->after('qst');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'gst', 'qst', 'total', 'amount_paid']);
        });
    }
};
