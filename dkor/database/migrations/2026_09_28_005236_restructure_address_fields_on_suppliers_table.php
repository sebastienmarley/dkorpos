<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['address', 'payment_address']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('address_civic')->nullable()->after('email');
            $table->string('address_apartment')->nullable()->after('address_civic');
            $table->string('address_street')->nullable()->after('address_apartment');
            $table->string('address_city')->nullable()->after('address_street');
            $table->string('address_province', 2)->nullable()->after('address_city');
            $table->string('address_country', 2)->nullable()->after('address_province');
            $table->string('address_postal_code')->nullable()->after('address_country');

            $table->string('payment_address_civic')->nullable()->after('bank_account');
            $table->string('payment_address_apartment')->nullable()->after('payment_address_civic');
            $table->string('payment_address_street')->nullable()->after('payment_address_apartment');
            $table->string('payment_address_city')->nullable()->after('payment_address_street');
            $table->string('payment_address_province', 2)->nullable()->after('payment_address_city');
            $table->string('payment_address_country', 2)->nullable()->after('payment_address_province');
            $table->string('payment_address_postal_code')->nullable()->after('payment_address_country');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'address_civic', 'address_apartment', 'address_street', 'address_city',
                'address_province', 'address_country', 'address_postal_code',
                'payment_address_civic', 'payment_address_apartment', 'payment_address_street',
                'payment_address_city', 'payment_address_province', 'payment_address_country',
                'payment_address_postal_code',
            ]);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('address')->nullable()->after('email');
            $table->string('payment_address')->nullable()->after('bank_account');
        });
    }
};
