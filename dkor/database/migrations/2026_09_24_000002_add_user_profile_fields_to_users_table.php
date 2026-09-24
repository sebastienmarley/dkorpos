<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('firstname')->nullable()->after('name');
            $table->string('lastname')->nullable()->after('firstname');
            $table->string('username')->nullable()->unique()->after('lastname');
            $table->string('role')->default('user')->after('username');
            $table->date('first_day')->nullable()->after('role');
            $table->date('last_day')->nullable()->after('first_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firstname', 'lastname', 'username', 'role', 'first_day', 'last_day']);
        });
    }
};
