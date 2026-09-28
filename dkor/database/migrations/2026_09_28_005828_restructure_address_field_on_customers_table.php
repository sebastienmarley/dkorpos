<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('adress');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('address_civic')->nullable()->after('email');
            $table->string('address_apartment')->nullable()->after('address_civic');
            $table->string('address_street')->nullable()->after('address_apartment');
            $table->string('address_city')->nullable()->after('address_street');
            $table->string('address_province', 2)->nullable()->after('address_city');
            $table->string('address_country', 2)->nullable()->after('address_province');
            $table->string('address_postal_code')->nullable()->after('address_country');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'address_civic', 'address_apartment', 'address_street', 'address_city',
                'address_province', 'address_country', 'address_postal_code',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('adress')->nullable()->after('email');
        });
    }
};
