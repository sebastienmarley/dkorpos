<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expédition par un fournisseur (issue #86) : les services gérés par l'application (frais d'expédition), les
     * limites de dimensions d'un colis expédiable par magasin et le moment où tous les articles d'une livraison sont
     * en main.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('shipping_max_length', 8, 2)->nullable()->after('custom_deposit_percent');
            $table->decimal('shipping_max_weight', 8, 2)->nullable()->after('shipping_max_length');
        });

        Schema::table('customer_order_deliveries', function (Blueprint $table) {
            $table->dateTime('ready_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_deliveries', function (Blueprint $table) {
            $table->dropColumn('ready_at');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['shipping_max_length', 'shipping_max_weight']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
