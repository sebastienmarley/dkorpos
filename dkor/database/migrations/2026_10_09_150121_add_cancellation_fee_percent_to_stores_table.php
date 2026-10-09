<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frais d'annulation (en % du prix vendant) facturés quand un client annule un article déjà commandé sans
     * attendre la confirmation du fournisseur.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('cancellation_fee_percent', 5, 2)->default(0)->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('cancellation_fee_percent');
        });
    }
};
