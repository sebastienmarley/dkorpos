<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventaire par magasin (issue #100, ADR 0012) : le stock, les unités FIFO, le journal, les ajustements, les
     * prises d'inventaire et les écritures d'inventaire appartiennent à un magasin; une commande fournisseur a un
     * magasin de destination, et une réception le magasin qui reçoit. Ce qui existe est attribué au premier magasin
     * physique.
     */
    public function up(): void
    {
        $tables = ['inventory_stocks', 'inventory_units', 'inventory_movements', 'inventory_adjustments', 'inventory_counts', 'supplier_orders', 'receptions', 'ledger_entries'];

        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropUnique(['product_id']);
        });

        foreach ($tables as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $column = $table->foreignId('store_id')->nullable()->after('id')->constrained()->restrictOnDelete();

                if (! in_array($name, ['ledger_entries', 'inventory_movements'], true)) {
                    $table->index('store_id');
                }
            });
        }

        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->unique(['product_id', 'store_id']);
        });

        $storeId = DB::table('stores')->where('type', 'physical')->orderBy('id')->value('id');

        if ($storeId !== null) {
            foreach (array_diff($tables, ['ledger_entries']) as $name) {
                DB::table($name)->whereNull('store_id')->update(['store_id' => $storeId]);
            }

            DB::table('ledger_entries')->whereNull('store_id')->where('reference_type', 'like', '%InventoryAdjustment')->update(['store_id' => $storeId]);
        }
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'store_id']);
        });

        foreach (['inventory_stocks', 'inventory_units', 'inventory_movements', 'inventory_adjustments', 'inventory_counts', 'supplier_orders', 'receptions', 'ledger_entries'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! in_array($name, ['ledger_entries', 'inventory_movements'], true)) {
                    $table->dropIndex(['store_id']);
                }

                $table->dropConstrainedForeignId('store_id');
            });
        }

        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->unique('product_id');
        });
    }
};
