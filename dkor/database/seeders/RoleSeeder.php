<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Crée les rôles de config/access.php et leur accorde leurs permissions. Un rôle créé reçoit toutes ses
     * permissions; un rôle existant ne reçoit que les permissions créées par cette exécution, pour ne pas
     * rétablir ce qui a été retiré depuis dans l'interface. Relancé à chaque déploiement.
     */
    public function run(): void
    {
        $createdPermissions = $this->resolve(PermissionSeeder::class)->__invoke();

        foreach (config('access.roles') as $name => $definition) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $definition['label'], 'level' => $definition['level']],
            );

            $permissions = $definition['permissions'] === '*'
                ? Permission::pluck('name')->all()
                : $definition['permissions'];

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($permissions);

                continue;
            }

            $newPermissions = array_values(array_intersect($permissions, $createdPermissions));

            if ($newPermissions !== []) {
                $role->givePermissionTo($newPermissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
