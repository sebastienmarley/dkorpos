<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les magasins existants sont tous au Québec.
        Schema::table('stores', function (Blueprint $table) {
            $table->string('province', 2)->default('QC')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('province');
        });
    }
};
