<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taux de change du jour de la réception (ADR 0016) : les unités reçues sont valorisées en CAD à ce taux.
     */
    public function up(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->default('1')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
