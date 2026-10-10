<?php

use App\Models\Role;
use Database\Seeders\RoleSeeder;

it('accorde une nouvelle permission aux rôles existants prévus par la config, sans rétablir ce qui a été retiré', function () {
    Role::findByName('salesman')->revokePermissionTo('customers.edit');
    config()->set('access.permissions', [
        ...config('access.permissions'),
        'reports.view' => ['Voir — rapports', 'Accéder à la page : rapports.'],
    ]);
    config()->push('access.roles.salesman.permissions', 'reports.view');

    $this->seed(RoleSeeder::class);

    $salesman = Role::findByName('salesman')->fresh();
    expect($salesman->hasPermissionTo('reports.view'))->toBeTrue()
        ->and($salesman->hasPermissionTo('customers.edit'))->toBeFalse();
    expect(Role::findByName('admin')->fresh()->hasPermissionTo('reports.view'))->toBeTrue();
    expect(Role::findByName('warehouse')->fresh()->hasPermissionTo('reports.view'))->toBeFalse();
});

it('ne réaccorde pas une permission existante retirée à un rôle', function () {
    Role::findByName('admin')->revokePermissionTo('users.view');

    $this->seed(RoleSeeder::class);

    expect(Role::findByName('admin')->fresh()->hasPermissionTo('users.view'))->toBeFalse();
});
