<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Répartit la quantité d'une ligne entre le stock réservé au client et la partie à commander.
     */
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->unsignedInteger('quantity_reserved')->default(0)->after('quantity');
            $table->unsignedInteger('quantity_on_order')->default(0)->after('quantity_reserved');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity_reserved', 'quantity_on_order']);
        });
    }
};
