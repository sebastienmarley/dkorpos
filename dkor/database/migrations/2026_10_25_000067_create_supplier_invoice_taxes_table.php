<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('tax_name');
            $table->integer('amount');
            $table->timestamps();

            $table->unique(['supplier_invoice_id', 'tax_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_taxes');
    }
};
