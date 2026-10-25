<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description_template')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->integer('selling_price')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->string('code')->nullable();

            $table->unique('code');
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
