<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Livraisons d'une commande client, communes à la livraison par nos camions (date et demi-journée) et à
     * l'expédition par un fournisseur. L'adresse confirmée est copiée sur la livraison; les articles à livrer sont
     * listés avec leur quantité.
     */
    public function up(): void
    {
        Schema::create('customer_order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('customer_order_deliveries')->nullOnDelete();
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
            $table->dateTime('address_verified_at');
            $table->boolean('pay_on_delivery')->default(false);
            $table->foreignId('pay_on_delivery_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'scheduled_date', 'period']);
        });

        Schema::create('customer_order_delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_order_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['customer_order_delivery_id', 'customer_order_line_id'], 'delivery_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_delivery_lines');
        Schema::dropIfExists('customer_order_deliveries');
    }
};
