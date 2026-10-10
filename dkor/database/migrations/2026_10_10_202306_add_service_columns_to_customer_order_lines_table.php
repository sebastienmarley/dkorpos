<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une ligne de commande client vend un produit ou un service (fournisseur choisi, ou interne), avec sa
     * description précise. is_taxable fige le statut fiscal au moment de la vente.
     */
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change();
            $table->foreignId('service_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('service_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable()->after('supplier_id');
            $table->boolean('is_taxable')->default(true)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['description', 'is_taxable']);
        });
    }
};
