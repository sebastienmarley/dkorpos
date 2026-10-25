<?php

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

it('donne un alias polymorphe à chaque modèle', function () {
    $models = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file): string => 'App\\Models\\'.basename($file, '.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract())
        ->values();

    expect(array_values(Relation::morphMap()))->toEqualCanonicalizing($models->all());
});

it('enregistre l\'alias plutôt que le nom de la classe dans les références', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();

    $movement = InventoryMovement::record($product, $store, null, InventoryStatus::InStock, 1, InventoryMovementType::Adjustment, $product);

    expect($movement->fresh()->reference_type)->toBe('product')
        ->and($movement->fresh()->reference->is($product))->toBeTrue();
});

it('enregistre l\'alias de l\'usager dans ses rôles', function () {
    $user = User::factory()->withRole('salesman')->create();

    expect(DB::table(config('permission.table_names.model_has_roles'))->where('model_id', $user->id)->value('model_type'))->toBe('user')
        ->and($user->fresh()->hasRole('salesman'))->toBeTrue();
});
