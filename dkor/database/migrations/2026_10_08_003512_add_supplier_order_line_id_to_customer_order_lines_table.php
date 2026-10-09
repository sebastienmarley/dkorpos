<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ligne de commande fournisseur qui couvre la quantité « en commande » d'une ligne de commande client.
     */
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->foreignId('supplier_order_line_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_order_line_id');
        });
    }
};
