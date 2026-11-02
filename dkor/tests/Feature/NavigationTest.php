<?php

use App\Models\Role;
use App\Models\User;
use App\Support\Navigation;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
});

/**
 * @return list<string>
 */
function navigationRoutes(User $user): array
{
    return array_column(array_merge(...array_column(app(Navigation::class)->sections($user), 'items')), 'route');
}

it('ne montre que les pages permises', function () {
    $user = User::factory()->withRole('visiteur')->create();
    $user->givePermissionTo(['customers.view', 'products.view']);

    expect(navigationRoutes($user))->toBe(['home', 'stats.index', 'customers.index', 'products.index']);
});

it('masque les sections sans page permise', function () {
    $user = User::factory()->withRole('visiteur')->create();
    $user->givePermissionTo('taxes.view');

    $headings = array_column(app(Navigation::class)->sections($user), 'heading');

    expect($headings)->toBe([null, 'Comptabilité']);
});

it('garde l\'accueil et trois pages prioritaires dans les onglets mobiles', function () {
    $user = User::factory()->withRole('visiteur')->create();
    $user->givePermissionTo(['customers.view', 'customer_orders.view', 'deliveries.handle', 'receptions.view', 'products.view']);

    $navigation = app(Navigation::class);
    $tabs = $navigation->bottomTabs($navigation->sections($user));

    expect(array_column($tabs, 'route'))->toBe(['home', 'customer-orders.index', 'deliveries.mine', 'receptions.index']);
});

it('affiche le menu, la recherche et les onglets dans le layout', function () {
    $user = User::factory()->withRole('visiteur')->create();
    $user->givePermissionTo('customers.view');

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-test="navigation-drawer"', false)
        ->assertSee('data-test="spotlight"', false)
        ->assertSee('data-test="bottom-tabs"', false)
        ->assertSee(route('customers.index'))
        ->assertDontSee(route('suppliers.index'));
});
