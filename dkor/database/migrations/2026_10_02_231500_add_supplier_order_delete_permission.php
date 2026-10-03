<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée la permission de suppression des commandes fournisseurs et l'accorde aux rôles existants.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permission = Permission::where('name', 'supplier_orders.delete')->firstOrFail();

        Role::all()->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        Permission::where('name', 'supplier_orders.delete')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
