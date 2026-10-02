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
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        Permission::where('name', 'like', '%.view')->get()->each(
            fn (Permission $permission) => Role::all()->each(fn (Role $role) => $role->givePermissionTo($permission)),
        );
    }

    public function down(): void
    {
        //
    }
};
