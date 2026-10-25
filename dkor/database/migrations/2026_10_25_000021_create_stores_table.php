<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('physical');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_civic', 20)->nullable();
            $table->string('address_apartment', 20)->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('address_province', 2)->nullable();
            $table->string('address_country', 2)->nullable();
            $table->string('address_postal_code', 6)->nullable();
            $table->string('bank_account')->nullable();
            $table->json('opening_hours')->nullable();
            $table->bigInteger('warehouse_store_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->bigInteger('shipping_warehouse_id')->nullable();
            $table->string('vacation_accrual_start', 5)->nullable();
            $table->string('sick_accrual_start', 5)->nullable();
            $table->smallInteger('sick_days_full_time')->nullable();
            $table->smallInteger('sick_days_part_time')->nullable();
            $table->decimal('cancellation_fee_percent', 5, 2)->default('0');
            $table->string('province', 2)->default('QC');
            $table->decimal('custom_deposit_percent', 5, 2)->default('50');
            $table->decimal('shipping_max_length', 8, 2)->nullable();
            $table->decimal('shipping_max_weight', 8, 2)->nullable();

            $table->foreign('shipping_warehouse_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('warehouse_store_id')->references('id')->on('stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
