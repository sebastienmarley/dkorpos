<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu d'expédition (issue #95) : le transporteur choisi, le coût de sa soumission, le numéro de suivi et l'envoi
     * d'une expédition, la facture du transporteur qui regroupe plusieurs envois et le détail de ses taxes (une
     * écriture au grand livre par taxe).
     */
    public function up(): void
    {
        Schema::table('customer_order_deliveries', function (Blueprint $table) {
            $table->foreignId('shipping_supplier_id')->nullable()->after('store_id')->constrained('suppliers')->nullOnDelete();
            $table->integer('shipping_cost')->nullable()->after('shipping_supplier_id');
            $table->string('tracking_number')->nullable()->after('shipping_cost');
            $table->dateTime('shipped_at')->nullable()->after('tracking_number');
            $table->foreignId('shipped_by')->nullable()->after('shipped_at')->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->after('shipped_by')->constrained()->restrictOnDelete();
        });

        Schema::create('supplier_invoice_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('tax_name');
            $table->integer('amount');
            $table->timestamps();

            $table->unique(['supplier_invoice_id', 'tax_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_taxes');

        Schema::table('customer_order_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_invoice_id');
            $table->dropConstrainedForeignId('shipped_by');
            $table->dropColumn(['shipped_at', 'tracking_number', 'shipping_cost']);
            $table->dropConstrainedForeignId('shipping_supplier_id');
        });
    }
};
