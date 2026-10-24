<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu du livreur (issue #94) : l'ordre de la route dans une demi-journée, le départ du camion et la remise au
     * client, avec le statut de la ligne avant le chargement (pour un retour), la quantité acceptée et la raison d'un
     * refus par ligne de livraison.
     */
    public function up(): void
    {
        Schema::table('customer_order_deliveries', function (Blueprint $table) {
            $table->unsignedInteger('route_position')->nullable()->after('period');
            $table->dateTime('departed_at')->nullable()->after('ready_at');
            $table->foreignId('departed_by')->nullable()->after('departed_at')->constrained('users')->nullOnDelete();
            $table->dateTime('delivered_at')->nullable()->after('departed_by');
            $table->foreignId('delivered_by')->nullable()->after('delivered_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('customer_order_delivery_lines', function (Blueprint $table) {
            $table->string('loaded_from_status')->nullable()->after('quantity');
            $table->unsignedInteger('accepted_quantity')->nullable()->after('loaded_from_status');
            $table->string('refusal_reason')->nullable()->after('accepted_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_delivery_lines', function (Blueprint $table) {
            $table->dropColumn(['loaded_from_status', 'accepted_quantity', 'refusal_reason']);
        });

        Schema::table('customer_order_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivered_by');
            $table->dropColumn('delivered_at');
            $table->dropConstrainedForeignId('departed_by');
            $table->dropColumn(['departed_at', 'route_position']);
        });
    }
};
