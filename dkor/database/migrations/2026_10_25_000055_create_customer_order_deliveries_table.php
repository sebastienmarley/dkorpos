<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('rescheduled_from_id')->nullable();
            $table->string('method');
            $table->string('status');
            $table->date('scheduled_date')->nullable();
            $table->string('period')->nullable();
            $table->string('address_civic');
            $table->string('address_apartment')->nullable();
            $table->string('address_street');
            $table->string('address_city');
            $table->string('address_province');
            $table->string('address_country');
            $table->string('address_postal_code');
            $table->foreignId('address_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('address_verified_at');
            $table->boolean('pay_on_delivery')->default(false);
            $table->foreignId('pay_on_delivery_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->timestamp('ready_at')->nullable();
            $table->integer('route_position')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->foreignId('departed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature_path')->nullable();
            $table->string('signed_by_name')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('undelivered_reason')->nullable();
            $table->timestamp('undelivered_at')->nullable();
            $table->foreignId('shipping_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->integer('shipping_cost')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained()->restrictOnDelete();

            $table->index(['store_id', 'scheduled_date', 'period']);
            $table->foreign('rescheduled_from_id')->references('id')->on('customer_order_deliveries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_deliveries');
    }
};
