<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frais d'annulation facturés au client sur une ligne annulée à sa demande (avant taxes, inclus au sous-total).
     */
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->decimal('cancellation_fee', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropColumn('cancellation_fee');
        });
    }
};
