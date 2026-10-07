<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée la permission de gestion des vendeurs d'une commande client et l'accorde à la gestion
     * (Directeur, Administrateur et Propriétaire).
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permission = Permission::where('name', 'customer_orders.assign_salespeople')->firstOrFail();

        Role::whereIn('name', ['manager', 'admin', 'owner'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'customer_orders.assign_salespeople')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
