<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée les permissions des taxes et les accorde aux rôles Administrateur, Propriétaire et Comptabilité.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissions = Permission::where('name', 'like', 'taxes.%')->get();

        Role::whereIn('name', ['admin', 'owner', 'accounting'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        // Sur une base neuve, une ancienne migration accorde déjà tous les « .view » à chaque rôle.
        Role::whereNotIn('name', ['admin', 'owner', 'accounting'])->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'taxes.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
