<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crée les styles de produits et leurs permissions, accordées aux rôles qui ont déjà l'équivalent pour les couleurs.
     */
    public function up(): void
    {
        Schema::create('styles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('style_id')->nullable()->after('color_id')->constrained()->nullOnDelete();
        });

        (new PermissionSeeder)->run();

        foreach (['view', 'create', 'edit'] as $action) {
            $permission = Permission::where('name', "styles.{$action}")->firstOrFail();

            Role::permission("colors.{$action}")->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'styles.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['style_id']);
            $table->dropColumn('style_id');
        });

        Schema::dropIfExists('styles');
    }
};
