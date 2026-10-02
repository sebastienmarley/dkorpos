<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('symbol');
            $table->boolean('is_archived')->default(false)->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('is_archived');
            $table->string('symbol', 5)->nullable()->after('name');
        });
    }
};
