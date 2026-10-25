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
            $table->foreignId('reception_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->integer('merchandise_total');
            $table->integer('freight_fee')->default(0);
            $table->integer('customs_fee')->default(0);
            $table->integer('taxes')->default(0);
            $table->integer('computed_total');
            $table->integer('invoice_total');
            $table->integer('variance')->default(0);
            $table->decimal('discount_percent', 5, 2)->default('0');
            $table->smallInteger('discount_days')->nullable();
            $table->date('discount_due_date')->nullable();
            $table->integer('discount_amount')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->boolean('discount_next_month')->default(false);
            $table->foreignId('supplier_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->integer('credit_requested')->default(0);
            $table->string('credit_note_number')->nullable();
            $table->date('credit_received_at')->nullable();

            $table->unique('reception_id');
            $table->unique(['supplier_id', 'invoice_number']);
            $table->unique('supplier_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
