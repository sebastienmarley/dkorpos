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
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (config('access.permissions') as $name => [$label, $description]) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $label, 'description' => $description],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
