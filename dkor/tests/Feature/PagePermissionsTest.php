<?php

use App\Models\Role;
use App\Models\User;

dataset('pages', [
    'customers.index' => ['customers.index', 'customers.view'],
    'customer-orders.index' => ['customer-orders.index', 'customer_orders.view'],
    'suppliers.index' => ['suppliers.index', 'suppliers.view'],
    'receptions.index' => ['receptions.index', 'receptions.view'],
    'receptions.create' => ['receptions.create', 'receptions.create'],
    'inventory.movements' => ['inventory.movements', 'inventory.view'],
    'accounting.invoices' => ['accounting.invoices', 'invoices.view'],
    'products.index' => ['products.index', 'products.view'],
    'parts.index' => ['parts.index', 'parts.view'],
    'supplier-orders.index' => ['supplier-orders.index', 'supplier_orders.view'],
    'catalog.classification (départements)' => ['catalog.classification', 'departments.view'],
    'catalog.classification (catégories)' => ['catalog.classification', 'categories.view'],
    'catalog.classification (couleurs)' => ['catalog.classification', 'colors.view'],
    'catalog.classification (styles)' => ['catalog.classification', 'styles.view'],
    'catalog.services' => ['catalog.services', 'services.view'],
    'labels.index' => ['labels.index', 'labels.view'],
    'catalog.price-lists' => ['catalog.price-lists', 'price_lists.view'],
    'accounting.taxes' => ['accounting.taxes', 'taxes.view'],
    'accounting.currencies' => ['accounting.currencies', 'currencies.view'],
    'schedules.index' => ['schedules.index', 'schedules.view'],
    'schedules.schedule-edit' => ['schedules.schedule-edit', 'schedule_management.view'],
    'schedules.templates' => ['schedules.templates', 'schedule_templates.view'],
    'schedules.holidays' => ['schedules.holidays', 'holidays.view'],
    'schedules.appointments' => ['schedules.appointments', 'appointments.view'],
    'users.index' => ['users.index', 'users.view'],
    'deliveries.index' => ['deliveries.index', 'deliveries.view'],
]);

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
});

it('refuse la page sans la permission', function (string $route, string $permission) {
    $this->actingAs(User::factory()->withRole('visiteur')->create())
        ->get(route($route))
        ->assertForbidden();
})->with('pages');

it('autorise la page avec la permission', function (string $route, string $permission) {
    $user = User::factory()->withRole('visiteur')->create();
    $user->givePermissionTo($permission);

    $this->actingAs($user)->get(route($route))->assertOk();
})->with('pages');

it('accorde les permissions de page selon config/access.php', function (string $route, string $permission) {
    foreach (config('access.roles') as $name => $definition) {
        if ($definition['permissions'] === '*' || in_array($permission, $definition['permissions'], true)) {
            expect(User::factory()->withRole($name)->create()->can($permission))->toBeTrue("{$name} / {$permission}");
        }
    }
})->with('pages');
