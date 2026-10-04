<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('vacation_accrual_start', 5)->nullable()->after('bank_account');
            $table->string('sick_accrual_start', 5)->nullable()->after('vacation_accrual_start');
            $table->unsignedSmallInteger('sick_days_full_time')->nullable()->after('sick_accrual_start');
            $table->unsignedSmallInteger('sick_days_part_time')->nullable()->after('sick_days_full_time');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['vacation_accrual_start', 'sick_accrual_start', 'sick_days_full_time', 'sick_days_part_time']);
        });
    }
};
