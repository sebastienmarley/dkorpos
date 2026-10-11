<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Montants du catalogue, des services et des pièces en cents (ADR 0008).
     *
     * @var array<string, array<string, array{nullable: bool, default: int|null}>>
     */
    private const COLUMNS = [
        'products' => [
            'cost' => ['nullable' => false, 'default' => null],
            'imap' => ['nullable' => true, 'default' => null],
        ],
        'price_list_items' => [
            'cost' => ['nullable' => true, 'default' => null],
            'imap' => ['nullable' => true, 'default' => null],
        ],
        'services' => [
            'selling_price' => ['nullable' => true, 'default' => null],
        ],
        'service_supplier' => [
            'cost' => ['nullable' => false, 'default' => 0],
            'selling_price' => ['nullable' => false, 'default' => 0],
        ],
        'parts' => [
            'last_cost' => ['nullable' => false, 'default' => 0],
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
                    $this->define($blueprint->decimal($column, 10, 2), $definition)->change();
                }
            });

            foreach (array_keys($columns) as $column) {
                DB::table($table)->whereNotNull($column)->update([$column => DB::raw("{$column} / 100.0")]);
            }
        }
    }

    /**
     * @param  array{nullable: bool, default: int|null}  $definition
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
