<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Articles reçus endommagés : partie de la quantité reçue qui entre en inventaire défectueux, et lien du dossier
     * défectueux vers la ligne de réception.
     */
    public function up(): void
    {
        Schema::table('reception_lines', function (Blueprint $table) {
            $table->unsignedInteger('quantity_damaged')->default(0)->after('quantity');
        });

        Schema::table('defective_products', function (Blueprint $table) {
            $table->foreignId('reception_line_id')->nullable()->after('supplier_order_line_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('defective_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reception_line_id');
        });

        Schema::table('reception_lines', function (Blueprint $table) {
            $table->dropColumn('quantity_damaged');
        });
    }
};
