<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signature du client à la livraison (issue #93) : l'image, le nom du signataire et le moment de la signature; la
     * raison d'une livraison « Non livrée » (client absent ou tout refusé); et les modes de paiement que le livreur
     * peut enregistrer à la livraison.
     */
    public function up(): void
    {
        Schema::table('customer_order_deliveries', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('delivered_by');
            $table->string('signed_by_name')->nullable()->after('signature_path');
            $table->dateTime('signed_at')->nullable()->after('signed_by_name');
            $table->string('undelivered_reason')->nullable()->after('signed_at');
            $table->dateTime('undelivered_at')->nullable()->after('undelivered_reason');
        });

        Schema::table('customer_payment_methods', function (Blueprint $table) {
            $table->boolean('is_usable_on_delivery')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('customer_payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_usable_on_delivery');
        });

        Schema::table('customer_order_deliveries', function (Blueprint $table) {
            $table->dropColumn(['signature_path', 'signed_by_name', 'signed_at', 'undelivered_reason', 'undelivered_at']);
        });
    }
};
