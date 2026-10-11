<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Erreurs d'expédition des fournisseurs : articles manquants (annoncés et facturés, mais non reçus), articles
     * reçus en trop, et demande de crédit sur la facture pour les manquants.
     */
    public function up(): void
    {
        Schema::table('reception_lines', function (Blueprint $table) {
            $table->unsignedInteger('quantity_short')->default(0)->after('quantity_damaged');
        });

        Schema::table('supplier_order_lines', function (Blueprint $table) {
            $table->unsignedInteger('quantity_short')->default(0)->after('quantity_received');
            $table->boolean('is_overship')->default(false)->after('quantity_short');
        });

        Schema::table('supplier_invoice_lines', function (Blueprint $table) {
            $table->unsignedInteger('quantity_short')->default(0)->after('quantity');
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->integer('credit_requested')->default(0)->after('discount_amount');
            $table->string('credit_note_number')->nullable()->after('credit_requested');
            $table->date('credit_received_at')->nullable()->after('credit_note_number');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn(['credit_requested', 'credit_note_number', 'credit_received_at']);
        });

        Schema::table('supplier_invoice_lines', function (Blueprint $table) {
            $table->dropColumn('quantity_short');
        });

        Schema::table('supplier_order_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity_short', 'is_overship']);
        });

        Schema::table('reception_lines', function (Blueprint $table) {
            $table->dropColumn('quantity_short');
        });
    }
};
