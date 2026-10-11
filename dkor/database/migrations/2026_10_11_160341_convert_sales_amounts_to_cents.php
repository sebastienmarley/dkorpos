<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Montants des ventes (commandes client, lignes, paiements, taxes, crédit des clients) en cents (ADR 0008).
     *
     * @var array<string, array<string, array{nullable: bool, default: int|null, precision: int}>>
     */
    private const COLUMNS = [
        'customer_orders' => [
            'subtotal' => ['nullable' => false, 'default' => 0, 'precision' => 12],
            'total' => ['nullable' => false, 'default' => 0, 'precision' => 12],
            'amount_paid' => ['nullable' => false, 'default' => 0, 'precision' => 12],
            'balance_due' => ['nullable' => false, 'default' => 0, 'precision' => 12],
        ],
        'customer_order_lines' => [
            'unit_price' => ['nullable' => false, 'default' => null, 'precision' => 10],
            'unit_cost' => ['nullable' => true, 'default' => null, 'precision' => 10],
            'cancellation_fee' => ['nullable' => true, 'default' => null, 'precision' => 10],
        ],
        'customer_order_payments' => [
            'amount' => ['nullable' => false, 'default' => null, 'precision' => 12],
            'rounding_adjustment' => ['nullable' => true, 'default' => null, 'precision' => 12],
            'cash_tendered' => ['nullable' => true, 'default' => null, 'precision' => 12],
            'change_given' => ['nullable' => true, 'default' => null, 'precision' => 12],
        ],
        'customer_order_taxes' => [
            'amount' => ['nullable' => false, 'default' => 0, 'precision' => 12],
        ],
        'customers' => [
            'credit_balance' => ['nullable' => false, 'default' => 0, 'precision' => 12],
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
