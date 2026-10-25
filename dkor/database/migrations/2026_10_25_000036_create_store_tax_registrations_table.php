<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_tax_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('tax_name', 50);
            $table->string('number');
            $table->timestamps();

            $table->unique(['store_id', 'tax_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_tax_registrations');
    }
};
