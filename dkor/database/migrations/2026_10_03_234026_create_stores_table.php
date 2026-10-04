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
            $table->enum('type', ['physical', 'virtual'])->default('physical');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_civic', 20)->nullable();
            $table->string('address_apartment', 20)->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('address_province', 2)->nullable();
            $table->string('address_country', 2)->nullable();
            $table->string('address_postal_code', 6)->nullable();
            $table->string('gst_number')->nullable();
            $table->string('qst_number')->nullable();
            $table->string('bank_account')->nullable();
            $table->json('opening_hours')->nullable();
            $table->foreignId('warehouse_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
