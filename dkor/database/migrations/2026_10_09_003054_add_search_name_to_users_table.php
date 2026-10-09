<?php

use App\Models\User;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('search_name')->nullable()->after('lastname')->index();
        });

        DB::table('users')->orderBy('id')->each(function (object $row): void {
            DB::table('users')->where('id', $row->id)->update([
                'search_name' => User::normalizeForSearch($row->firstname.' '.$row->lastname),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['search_name']);
            $table->dropColumn('search_name');
        });
    }
};
