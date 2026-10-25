<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->string('code')->nullable();
            $table->boolean('is_usable_on_delivery')->default(false);

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_methods');
    }
};
