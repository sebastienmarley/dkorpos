<?php

use App\Models\customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prénom et nom normalisés (minuscules, sans accents) pour une recherche indépendante de la casse et des accents.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('search_name')->nullable()->after('lastname')->index();
        });

        DB::table('customers')->orderBy('id')->each(function (object $row): void {
            DB::table('customers')->where('id', $row->id)->update([
                'search_name' => customer::normalizeForSearch($row->firstname.' '.$row->lastname),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['search_name']);
            $table->dropColumn('search_name');
        });
    }
};
