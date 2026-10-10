<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une ligne de commande (client ou fournisseur) peut porter une pièce de remplacement au lieu d'un produit. Une
     * pièce liée à une commande ne peut pas être supprimée.
     */
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->foreignId('part_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
        });

        Schema::table('supplier_order_lines', function (Blueprint $table) {
            $table->foreignId('part_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('part_id');
        });

        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('part_id');
        });
    }
};
