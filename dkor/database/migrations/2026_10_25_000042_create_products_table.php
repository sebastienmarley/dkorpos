<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->integer('cost');
            $table->timestamps();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('color_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_discontinued')->default(false);
            $table->boolean('is_non_orderable')->default(false);
            $table->string('supplier_model')->nullable();
            $table->string('clean_model')->nullable();
            $table->string('collection')->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->integer('imap')->nullable();
            $table->string('supplier_clean_model')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->foreignId('style_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('displayed_price')->nullable();

            $table->index('supplier_clean_model');
            $table->unique(['supplier_id', 'clean_model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
