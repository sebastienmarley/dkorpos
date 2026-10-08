<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paiements reçus d'un client sur une commande; un même paiement peut être réparti sur plusieurs modes
     * (une ligne par mode). Lié au ramassage qui l'a exigé, le cas échéant.
     */
    public function up(): void
    {
        Schema::create('customer_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_order_pickup_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_payments');
    }
};
