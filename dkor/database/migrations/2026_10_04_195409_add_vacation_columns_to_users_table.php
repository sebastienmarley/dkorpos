<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('vacation_hours_per_day', 4, 2)->nullable();
            $table->decimal('vacation_days_accrued', 6, 2)->nullable();
            $table->decimal('vacation_hours_available', 7, 2)->nullable();
            $table->dateTime('vacation_balance_computed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['vacation_hours_per_day', 'vacation_days_accrued', 'vacation_hours_available', 'vacation_balance_computed_at']);
        });
    }
};
