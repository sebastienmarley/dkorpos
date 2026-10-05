<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_list_lists', function (Blueprint $table) {
            $table->timestamp('applied_at')->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('price_list_lists', function (Blueprint $table) {
            $table->dropColumn('applied_at');
        });
    }
};
