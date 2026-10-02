<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        (new RoleSeeder)->run();

        $roleIds = DB::table(config('permission.table_names.roles'))->pluck('id', 'name');

        DB::table('users')->select('id', 'role')->orderBy('id')->each(function (object $user) use ($roleIds): void {
            $roleId = $roleIds[$user->role] ?? $roleIds['salesman'];

            DB::table(config('permission.table_names.model_has_roles'))->insert([
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('salesman')->after('username');
        });

        $names = DB::table(config('permission.table_names.roles'))->pluck('name', 'id');

        DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', 'App\\Models\\User')
            ->orderBy('model_id')
            ->each(function (object $row) use ($names): void {
                DB::table('users')->where('id', $row->model_id)->update(['role' => $names[$row->role_id]]);
            });

        DB::table(config('permission.table_names.model_has_roles'))->where('model_type', 'App\\Models\\User')->delete();
    }
};
