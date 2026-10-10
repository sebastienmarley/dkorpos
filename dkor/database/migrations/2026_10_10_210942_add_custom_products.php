<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produits sur mesure : le produit sert de gabarit; chaque vente porte ses spécifications, le coût soumis par
     * le fournisseur (et son numéro de soumission) et un prix saisi. Le magasin fixe le dépôt exigé sur le sur-mesure.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('is_taxable');
        });

        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('is_taxable');
            $table->decimal('unit_cost', 10, 2)->nullable()->after('unit_price');
            $table->string('quote_number')->nullable()->after('unit_cost');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('custom_deposit_percent', 5, 2)->default(50)->after('cancellation_fee_percent');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('custom_deposit_percent');
        });

        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropColumn(['is_custom', 'unit_cost', 'quote_number']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_custom');
        });
    }
};
