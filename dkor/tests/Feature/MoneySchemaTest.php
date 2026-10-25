<?php

/*
|--------------------------------------------------------------------------
| Test de garde : aucun montant en décimal (ADR 0008)
|--------------------------------------------------------------------------
|
| Un montant d'argent est un entier en cents, casté en Money. Seuls les taux, pourcentages, multiplicateurs et
| mesures restent en décimal. Une nouvelle colonne décimale fait échouer ce test : si c'est un montant, il faut un
| entier et le MoneyCast; si c'est un taux ou une mesure, il faut l'ajouter à la liste ci-dessous.
|
*/

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

it('ne garde en décimal que les taux, pourcentages, multiplicateurs et mesures', function () {
    $decimalColumns = collect(Schema::getTableListing(schemaQualified: false))
        ->flatMap(fn (string $table) => collect(Schema::getColumns($table))
            ->filter(fn (array $column): bool => in_array($column['type_name'], ['numeric', 'decimal', 'float', 'double', 'real'], true))
            ->map(fn (array $column): string => "{$table}.{$column['name']}"))
        ->sort()
        ->values()
        ->all();

    expect($decimalColumns)->toBe(collect([
        'currencies.rate',
        'customer_order_taxes.rate',
        'price_list_items.height',
        'price_list_items.length',
        'price_list_items.weight',
        'price_list_items.width',
        'price_list_lists.discount_percent',
        'products.height',
        'products.length',
        'products.weight',
        'products.width',
        'stores.cancellation_fee_percent',
        'stores.custom_deposit_percent',
        'stores.shipping_max_length',
        'stores.shipping_max_weight',
        'supplier_invoices.discount_percent',
        'suppliers.base_multiplier',
        'suppliers.customs_fee',
        'suppliers.early_payment_discount_percent',
        'suppliers.price_multiplier',
        'suppliers.shipping_fee',
        'taxes.rate',
        'users.commission_rate',
        'users.hours_per_day',
        'users.vacation_days_accrued',
        'users.vacation_hours_available',
    ])->sort()->values()->all());
});

it('stocke chaque montant casté en Money dans une colonne entière', function () {
    $moneyColumns = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file): string => 'App\\Models\\'.basename($file, '.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract())
        ->flatMap(function (string $class) {
            $model = new $class;

            return collect($model->getCasts())
                ->filter(fn (string $cast): bool => $cast === MoneyCast::class)
                ->keys()
                ->map(fn (string $column): array => [$model->getTable(), $column]);
        });

    expect($moneyColumns)->toHaveCount(43);

    foreach ($moneyColumns as [$table, $column]) {
        expect(Schema::getColumnType($table, $column))->toBeIn(['integer', 'int4'], "{$table}.{$column}");
    }
});
