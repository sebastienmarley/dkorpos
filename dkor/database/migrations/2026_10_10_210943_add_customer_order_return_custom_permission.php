<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée l'autorisation de reprendre un article sur mesure en retour et l'accorde à la gestion
     * (Directeur, Administrateur et Propriétaire).
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permission = Permission::where('name', 'customer_orders.return_custom')->firstOrFail();

        Role::whereIn('name', ['manager', 'admin', 'owner'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'customer_orders.return_custom')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
