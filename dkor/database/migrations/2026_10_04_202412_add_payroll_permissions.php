<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée payroll.view / payroll.lock et les accorde aux rôles Administrateur, Propriétaire et Comptabilité.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissions = Permission::whereIn('name', ['payroll.view', 'payroll.lock'])->get();

        Role::whereIn('name', ['admin', 'owner', 'accounting'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
