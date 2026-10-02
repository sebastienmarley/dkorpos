<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Retire le rôle « kitchen » s'il existe et qu'aucun usager ne l'a (un rôle attribué est conservé).
     */
    public function up(): void
    {
        Role::where('name', 'kitchen')->doesntHave('users')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
