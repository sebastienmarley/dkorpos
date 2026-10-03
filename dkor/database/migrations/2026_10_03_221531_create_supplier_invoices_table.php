<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('reception_id')->unique()->constrained()->restrictOnDelete();
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->decimal('merchandise_total', 12, 2);
            $table->decimal('freight_fee', 12, 2)->default(0);
            $table->decimal('customs_fee', 12, 2)->default(0);
            $table->decimal('taxes', 12, 2)->default(0);
            $table->decimal('computed_total', 12, 2);
            $table->decimal('invoice_total', 12, 2);
            $table->decimal('variance', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('discount_days')->nullable();
            $table->date('discount_due_date')->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
