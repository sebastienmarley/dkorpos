<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('product');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->string('email')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('order_email')->nullable();
            $table->boolean('orderable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->decimal('price_multiplier', 8, 4)->default('1');
            $table->string('address_civic')->nullable();
            $table->string('address_apartment')->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_province', 2)->nullable();
            $table->string('address_country', 2)->nullable();
            $table->string('address_postal_code')->nullable();
            $table->string('payment_address_civic')->nullable();
            $table->string('payment_address_apartment')->nullable();
            $table->string('payment_address_street')->nullable();
            $table->string('payment_address_city')->nullable();
            $table->string('payment_address_province', 2)->nullable();
            $table->string('payment_address_country', 2)->nullable();
            $table->string('payment_address_postal_code')->nullable();
            $table->decimal('base_multiplier', 8, 4)->default('2');
            $table->decimal('customs_fee', 8, 4)->default('0');
            $table->decimal('shipping_fee', 8, 4)->default('0');
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('prepaid_amount')->nullable()->default(0);
            $table->boolean('collect')->default(false);
            $table->bigInteger('default_shipping_supplier_id')->nullable();
            $table->decimal('early_payment_discount_percent', 5, 2)->default('0');
            $table->smallInteger('early_payment_discount_days')->nullable();
            $table->boolean('early_payment_next_month')->default(false);
            $table->boolean('custom_made')->default(false);

            $table->foreign('default_shipping_supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
