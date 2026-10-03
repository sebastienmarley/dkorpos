<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une facture couvre soit une réception (produits), soit une commande de services; les champs de facture
     * directement sur la commande disparaissent.
     */
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->foreignId('reception_id')->nullable()->change();
            $table->foreignId('supplier_order_id')->nullable()->unique()->after('reception_id')
                ->constrained()->restrictOnDelete();
        });

        Schema::table('supplier_invoice_lines', function (Blueprint $table) {
            $table->foreignId('reception_line_id')->nullable()->change();
            $table->foreignId('supplier_order_line_id')->nullable()->after('reception_line_id')
                ->constrained()->restrictOnDelete();
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'invoice_date', 'invoice_total']);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_total', 12, 2)->nullable();
        });

        Schema::table('supplier_invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_order_line_id');
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_order_id');
        });
    }
};
