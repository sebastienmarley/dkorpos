<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('phone')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->string('address_civic')->nullable();
            $table->string('address_apartment')->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_province', 2)->nullable();
            $table->string('address_country', 2)->nullable();
            $table->string('address_postal_code')->nullable();
            $table->string('search_name')->nullable();
            $table->integer('credit_balance')->default(0);

            $table->unique('email');
            $table->index('search_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
