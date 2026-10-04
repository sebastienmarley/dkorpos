<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permissions retirées à un usager même si son rôle les lui donne.
     */
    public function up(): void
    {
        Schema::create('user_denied_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained(config('permission.table_names.permissions'))->cascadeOnDelete();

            $table->primary(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_denied_permissions');
    }
};
