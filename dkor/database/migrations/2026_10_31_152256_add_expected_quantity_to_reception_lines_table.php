<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quantité attendue (bordereau du fournisseur) et vérification de chaque ligne d'une réception. Les lignes
     * existantes attendaient ce qui a été saisi, et sont considérées vérifiées.
     */
    public function up(): void
    {
        Schema::table('reception_lines', function (Blueprint $table) {
            $table->integer('quantity_expected')->default(0)->after('quantity');
            $table->timestamp('checked_at')->nullable()->after('quantity_short');
        });

        DB::table('reception_lines')->update([
            'quantity_expected' => DB::raw('quantity + quantity_short'),
            'checked_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('reception_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity_expected', 'checked_at']);
        });
    }
};
