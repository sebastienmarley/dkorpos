<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée les permissions de la facturation fournisseurs et les accorde aux rôles existants.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissions = Permission::where('name', 'like', 'invoices.%')->get();

        Role::all()->each(fn (Role $role) => $role->givePermissionTo($permissions));
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'invoices.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
