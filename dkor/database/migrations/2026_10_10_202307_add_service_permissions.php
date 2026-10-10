<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée les permissions du catalogue de services : la consultation pour les rôles qui vendent, la gestion pour
     * la gestion (Directeur, Administrateur, Propriétaire) et la comptabilité.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $view = Permission::where('name', 'services.view')->firstOrFail();
        $manage = Permission::whereIn('name', ['services.create', 'services.edit'])->get();

        Role::permission('customer_orders.view')->get()->each(fn (Role $role) => $role->givePermissionTo($view));
        Role::whereIn('name', ['admin', 'owner', 'manager', 'accounting'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$view, ...$manage]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'services.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
