<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée les permissions du journal d'inventaire et les accorde aux rôles existants.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissions = Permission::where('name', 'like', 'inventory.%')->get();

        Role::all()->each(fn (Role $role) => $role->givePermissionTo($permissions));
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'inventory.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
