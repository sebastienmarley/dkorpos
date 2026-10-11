<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Montants des fournisseurs, commandes, réceptions, unités d'inventaire et factures en cents (ADR 0008).
     *
     * @var array<string, array<string, array{nullable: bool, default: int|null, precision: int}>>
     */
    private const COLUMNS = [
        'suppliers' => [
            'prepaid_amount' => ['nullable' => true, 'default' => 0, 'precision' => 10],
        ],
        'supplier_order_lines' => [
            'unit_cost' => ['nullable' => false, 'default' => null, 'precision' => 10],
        ],
        'reception_lines' => [
            'unit_cost' => ['nullable' => false, 'default' => null, 'precision' => 10],
        ],
        'inventory_units' => [
            'cost' => ['nullable' => false, 'default' => null, 'precision' => 10],
        ],
        'supplier_invoices' => [
            'merchandise_total' => ['nullable' => false, 'default' => null, 'precision' => 12],
            'freight_fee' => ['nullable' => false, 'default' => 0, 'precision' => 12],
            'customs_fee' => ['nullable' => false, 'default' => 0, 'precision' => 12],
            'taxes' => ['nullable' => false, 'default' => 0, 'precision' => 12],
            'computed_total' => ['nullable' => false, 'default' => null, 'precision' => 12],
            'invoice_total' => ['nullable' => false, 'default' => null, 'precision' => 12],
            'variance' => ['nullable' => false, 'default' => 0, 'precision' => 12],
            'discount_amount' => ['nullable' => false, 'default' => 0, 'precision' => 12],
        ],
        'supplier_invoice_lines' => [
            'unit_cost' => ['nullable' => false, 'default' => null, 'precision' => 10],
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                DB::table($table)->whereNotNull($column)->update([$column => DB::raw("ROUND({$column} * 100)")]);
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column => $definition) {
                    $this->define($blueprint->integer($column), $definition)->change();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column => $definition) {
                    $this->define($blueprint->decimal($column, $definition['precision'], 2), $definition)->change();
                }
            });

            foreach (array_keys($columns) as $column) {
                DB::table($table)->whereNotNull($column)->update([$column => DB::raw("{$column} / 100.0")]);
            }
        }
    }

    /**
     * @param  array{nullable: bool, default: int|null, precision: int}  $definition
     */
    private function define(ColumnDefinition $column, array $definition): ColumnDefinition
    {
        $column->nullable($definition['nullable']);

        if ($definition['default'] !== null) {
            $column->default($definition['default']);
        }

        return $column;
    }
};
