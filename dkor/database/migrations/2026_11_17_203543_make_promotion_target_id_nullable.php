<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une cible « Tout le magasin » (target_type = all) ne désigne aucun enregistrement : son target_id est vide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_targets', function (Blueprint $table) {
            $table->unsignedBigInteger('target_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('promotion_targets', function (Blueprint $table) {
            $table->unsignedBigInteger('target_id')->nullable(false)->change();
        });
    }
};
