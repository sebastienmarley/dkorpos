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

        // Permissions d'actions de l'époque. Seules celles créées ici sont accordées à tous les rôles :
        // sur une installation neuve, RoleSeeder les a déjà réparties selon config/access.php.
        $actions = [
            'customers.create', 'customers.edit', 'suppliers.create', 'suppliers.edit', 'products.create', 'products.edit',
            'departments.create', 'departments.edit', 'categories.create', 'categories.edit', 'colors.create', 'colors.edit',
            'schedule_management.edit', 'schedule_management.publish',
            'schedule_templates.create', 'schedule_templates.edit', 'schedule_templates.delete',
            'holidays.create', 'holidays.edit', 'holidays.delete',
            'appointments.create', 'appointments.edit', 'appointments.delete',
        ];

        $existing = Permission::whereIn('name', $actions)->pluck('name')->all();

        (new PermissionSeeder)->run();

        $created = Permission::whereIn('name', array_diff($actions, $existing))->get();

        Role::all()->each(fn (Role $role) => $role->givePermissionTo($created));
    }

    public function down(): void
    {
        foreach (['users.edit' => 'users.update', 'users.edit_self' => 'users.update_self'] as $new => $old) {
            Permission::where('name', $new)->update(['name' => $old]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
