<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée users.view_hr / users.edit_hr et les accorde aux rôles Administrateur, Propriétaire et Comptabilité
     * (le Directeur ne les reçoit pas).
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissions = Permission::whereIn('name', ['users.view', 'users.view_hr', 'users.edit_hr'])->get();

        Role::whereIn('name', ['admin', 'owner', 'accounting'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
