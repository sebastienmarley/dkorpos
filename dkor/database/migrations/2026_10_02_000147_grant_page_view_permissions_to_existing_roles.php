<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Les pages exigent désormais une permission « xxx.view » : on l'accorde à tous les rôles
     * existants pour conserver l'accès actuel, les restrictions se font ensuite dans l'interface.
     *
     * Seules les permissions créées par cette migration sont accordées : sur une installation neuve,
     * RoleSeeder les a déjà réparties selon config/access.php et il ne faut pas les élargir.
     */
    public function up(): void
    {
        $pageViews = [
            'customers.view', 'suppliers.view', 'products.view', 'departments.view', 'categories.view', 'colors.view',
            'schedules.view', 'schedule_management.view', 'schedule_templates.view', 'holidays.view', 'appointments.view',
        ];

        $existing = Permission::whereIn('name', $pageViews)->pluck('name')->all();

        (new PermissionSeeder)->run();

        $created = Permission::whereIn('name', array_diff($pageViews, $existing))->get();

        Role::all()->each(fn (Role $role) => $role->givePermissionTo($created));
    }

    public function down(): void
    {
        //
    }
};
