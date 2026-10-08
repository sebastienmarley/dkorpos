<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée les permissions des modes de paiement et les accorde aux rôles Administrateur, Propriétaire et Comptabilité.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissions = Permission::where('name', 'like', 'payment_methods.%')->get();

        Role::whereIn('name', ['admin', 'owner', 'accounting'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'payment_methods.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
