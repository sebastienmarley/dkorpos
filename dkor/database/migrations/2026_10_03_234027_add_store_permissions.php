<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée les permissions de la gestion des magasins et les réserve aux rôles propriétaire et administrateur.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissions = Permission::where('name', 'like', 'stores.%')->get();

        Role::whereIn('name', ['owner', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        // Sur une base neuve, une ancienne migration accorde déjà tous les « .view » à chaque rôle.
        Role::whereNotIn('name', ['owner', 'admin'])->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permissions));
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'stores.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
