<?php

use App\Models\Color;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;

it('affiche l\'étiquette de prix du produit', function () {
    $this->actingAs(User::factory()->create());
    $supplier = Supplier::factory()->create(['name' => 'Meubles Laurier']);
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'model' => 'SOFA-200',
        'collection' => 'Nordik',
        'color_id' => Color::factory()->create(['name' => 'Charbon'])->id,
        'cost' => Money::parse(1200),
    ]);

    // Au-delà de 100 $, le prix de vente est un nombre entier de dollars : affiché sans les cents.
    $price = number_format(intdiv($product->selling_price->cents(), 100)).' $';

    $this->get(route('products.label', $product))
        ->assertOk()
        ->assertSeeInOrder([$price, 'Meubles Laurier', 'Nordik', 'Charbon', 'SOFA-200', (string) $product->id]);
});

it('refuse l\'étiquette sans la permission des produits', function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->actingAs(User::factory()->withRole('visiteur')->create());

    $this->get(route('products.label', Product::factory()->create()))->assertForbidden();
});
