<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3);
            $table->string('name');
            $table->timestamps();
            $table->decimal('rate', 12, 6)->default('1');
            $table->boolean('is_archived')->default(false);

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
