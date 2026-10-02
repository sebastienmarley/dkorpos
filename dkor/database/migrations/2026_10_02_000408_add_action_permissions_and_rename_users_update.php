<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * users.update(_self) devient users.edit(_self) (convention view/create/edit/delete), puis les
     * permissions d'actions des pages sont créées et accordées à tous les rôles existants
     * pour conserver l'accès actuel.
     */
    public function up(): void
    {
        foreach (['users.update' => 'users.edit', 'users.update_self' => 'users.edit_self'] as $old => $new) {
            Permission::where('name', $old)->update(['name' => $new]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        (new PermissionSeeder)->run();

        $permissions = Permission::whereIn('name', config('access.page_access'))->get();

        Role::all()->each(fn (Role $role) => $role->givePermissionTo($permissions));
    }

    public function down(): void
    {
        foreach (['users.edit' => 'users.update', 'users.edit_self' => 'users.update_self'] as $new => $old) {
            Permission::where('name', $new)->update(['name' => $old]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
