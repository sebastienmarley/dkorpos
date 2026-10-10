<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Crée les permissions de config/access.php. Les permissions existantes ne sont pas écrasées
     * (libellé et description modifiés dans l'interface conservés).
     *
     * @return list<string> Noms des permissions créées par cette exécution.
     */
    public function run(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $created = [];

        foreach (config('access.permissions') as $name => [$label, $description]) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $label, 'description' => $description],
            );

            if ($permission->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }
}
