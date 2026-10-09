<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Type de mouvement d'argent (paiement, remboursement, crédit porté au compte du client). Un crédit porté au
     * compte n'a pas de mode de paiement.
     */
    public function up(): void
    {
        Schema::table('customer_order_payments', function (Blueprint $table) {
            $table->string('type')->default('payment')->after('customer_payment_method_id');
            $table->foreignId('customer_payment_method_id')->nullable()->change();
        });

        DB::table('customer_order_payments')->where('amount', '<', 0)->update(['type' => 'refund']);
    }

    public function down(): void
    {
        Schema::table('customer_order_payments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
