<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée les permissions des commandes clients et les accorde aux rôles existants
     * pour conserver l'accès actuel.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissions = Permission::where('name', 'like', 'customer_orders.%')->get();

        Role::all()->each(fn (Role $role) => $role->givePermissionTo($permissions));
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'customer_orders.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
