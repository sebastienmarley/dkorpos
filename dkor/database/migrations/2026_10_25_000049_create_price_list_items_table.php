<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model');
            $table->string('clean_model');
            $table->integer('cost')->nullable();
            $table->integer('imap')->nullable();
            $table->string('upc')->nullable();
            $table->string('collection')->nullable();
            $table->text('description')->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->timestamps();

            $table->index('clean_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
    }
};
