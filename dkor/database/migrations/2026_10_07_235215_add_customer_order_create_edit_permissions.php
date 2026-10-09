<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée les permissions de création et de modification des commandes clients et les accorde
     * aux rôles qui ont déjà accès à la page.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissions = Permission::whereIn('name', ['customer_orders.create', 'customer_orders.edit'])->get();

        Role::permission('customer_orders.view')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['customer_orders.create', 'customer_orders.edit'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
