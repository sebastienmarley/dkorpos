<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pièce remise sans frais au client (ex. : sous garantie) : son prix vendant est de 0 $.
     */
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->boolean('is_no_charge')->default(false)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropColumn('is_no_charge');
        });
    }
};
