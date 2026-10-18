<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hiérarchie stricte Département > Catégorie : toute catégorie appartient à un département
 * et le produit ne référence plus que sa catégorie (le département en est déduit).
 */
return new class extends Migration
{
    private const string Unclassified = 'Non classé';

    public function up(): void
    {
        if (DB::table('categories')->whereNull('department_id')->exists()) {
            DB::table('categories')
                ->whereNull('department_id')
                ->update(['department_id' => $this->findOrCreateId('departments', ['name' => self::Unclassified])]);
        }

        DB::table('products')
            ->whereNotNull('department_id')
            ->whereNull('category_id')
            ->distinct()
            ->pluck('department_id')
            ->each(function (int $departmentId): void {
                DB::table('products')
                    ->where('department_id', $departmentId)
                    ->whereNull('category_id')
                    ->update(['category_id' => $this->findOrCreateId('categories', ['department_id' => $departmentId, 'name' => self::Unclassified])]);
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable(false)->change();
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->change();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });

        DB::table('products')->whereNotNull('category_id')->update([
            'department_id' => DB::table('categories')->select('department_id')->whereColumn('categories.id', 'products.category_id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function findOrCreateId(string $table, array $attributes): int
    {
        return DB::table($table)->where($attributes)->value('id')
            ?? DB::table($table)->insertGetId([...$attributes, 'created_at' => now(), 'updated_at' => now()]);
    }
};
