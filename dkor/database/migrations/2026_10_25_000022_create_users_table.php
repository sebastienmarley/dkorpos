<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->boolean('is_active')->default(true);
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('username')->nullable();
            $table->date('first_day')->nullable();
            $table->date('last_day')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cellphone')->nullable();
            $table->timestamp('last_modified')->nullable();
            $table->bigInteger('last_modified_by')->nullable();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address_civic')->nullable();
            $table->string('address_apartment')->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_province', 2)->nullable();
            $table->string('address_country', 2)->nullable();
            $table->string('address_postal_code')->nullable();
            $table->boolean('is_full_time')->default(false);
            $table->boolean('has_group_insurance')->default(false);
            $table->string('insurance_plan')->nullable();
            $table->boolean('is_salaried')->default(false);
            $table->integer('hourly_rate')->nullable();
            $table->integer('weekly_salary')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->boolean('has_commission')->default(false);
            $table->boolean('has_bonus')->default(false);
            $table->integer('weekly_sales_target')->nullable();
            $table->integer('bonus_amount')->nullable();
            $table->integer('bonus_step')->nullable();
            $table->decimal('hours_per_day', 4, 2)->nullable();
            $table->decimal('vacation_days_accrued', 6, 2)->nullable();
            $table->decimal('vacation_hours_available', 7, 2)->nullable();
            $table->timestamp('vacation_balance_computed_at')->nullable();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('search_name')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->unique('email');
            $table->index('search_name');
            $table->unique('username');
            $table->foreign('last_modified_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
