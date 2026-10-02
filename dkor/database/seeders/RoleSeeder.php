<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Crée les rôles de config/access.php. Les permissions d'un rôle ne sont attribuées qu'à sa création,
     * pour ne pas écraser ce qui a été modifié depuis dans l'interface.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        foreach (config('access.roles') as $name => $definition) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $definition['label'], 'level' => $definition['level']],
            );

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($definition['permissions'] === '*'
                    ? Permission::all()
                    : $definition['permissions']);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
