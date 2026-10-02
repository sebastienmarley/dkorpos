<?php

use App\Models\Role;
use App\Models\User;

dataset('pages', [
    'customers.index' => ['customers.index', 'customers.view'],
    'suppliers.index' => ['suppliers.index', 'suppliers.view'],
    'products.index' => ['products.index', 'products.view'],
    'catalog.departments' => ['catalog.departments', 'departments.view'],
    'catalog.categories' => ['catalog.categories', 'categories.view'],
    'catalog.colors' => ['catalog.colors', 'colors.view'],
    'accounting.currencies' => ['accounting.currencies', 'currencies.view'],
    'schedules.index' => ['schedules.index', 'schedules.view'],
    'schedules.schedule-edit' => ['schedules.schedule-edit', 'schedule_management.view'],
    'schedules.templates' => ['schedules.templates', 'schedule_templates.view'],
    'schedules.holidays' => ['schedules.holidays', 'holidays.view'],
    'schedules.appointments' => ['schedules.appointments', 'appointments.view'],
    'users.index' => ['users.index', 'users.view'],
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

it('accorde les permissions de page aux rôles par défaut', function (string $route, string $permission) {
    expect(User::factory()->create()->can($permission))->toBeTrue();
})->with('pages');
