<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->unsignedSmallInteger('level')->default(0)->after('label');
        });

        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('description')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->dropColumn(['label', 'level']);
        });

        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
    }
};
