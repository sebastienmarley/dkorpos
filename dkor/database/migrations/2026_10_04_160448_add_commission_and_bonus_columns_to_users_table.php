<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_commission')->default(false);
            $table->boolean('has_bonus')->default(false);
            $table->unsignedInteger('weekly_sales_target')->nullable();
            $table->unsignedInteger('bonus_amount')->nullable();
            $table->unsignedInteger('bonus_step')->nullable();
        });

        DB::table('users')->whereNotNull('commission_rate')->update(['has_commission' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['has_commission', 'has_bonus', 'weekly_sales_target', 'bonus_amount', 'bonus_step']);
        });
    }
};
