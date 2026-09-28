<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('clean_model')->nullable()->after('model');
            $table->unique(['supplier_id', 'clean_model']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['supplier_id', 'clean_model']);
            $table->dropColumn('clean_model');
        });
    }
};
