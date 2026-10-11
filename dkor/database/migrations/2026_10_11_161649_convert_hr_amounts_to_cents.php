<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taux horaire et salaire hebdomadaire des employés en cents (ADR 0008).
     *
     * @var list<string>
     */
    private const COLUMNS = ['hourly_rate', 'weekly_salary'];

    public function up(): void
    {
        foreach (self::COLUMNS as $column) {
            DB::table('users')->whereNotNull($column)->update([$column => DB::raw("ROUND({$column} * 100)")]);
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->integer($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->decimal($column, 8, 2)->nullable()->change();
            }
        });

        foreach (self::COLUMNS as $column) {
            DB::table('users')->whereNotNull($column)->update([$column => DB::raw("{$column} / 100.0")]);
        }
    }
};
