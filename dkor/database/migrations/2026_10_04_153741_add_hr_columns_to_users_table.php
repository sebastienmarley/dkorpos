<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_full_time')->default(false);
            $table->boolean('has_group_insurance')->default(false);
            $table->string('insurance_plan')->nullable();
            $table->boolean('is_salaried')->default(false);
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->decimal('weekly_salary', 8, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_full_time', 'has_group_insurance', 'insurance_plan', 'is_salaried',
                'hourly_rate', 'weekly_salary', 'commission_rate',
            ]);
        });
    }
};
