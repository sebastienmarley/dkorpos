<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paiement fait à un fournisseur (#132, ADR 0016) : montant dans la devise du fournisseur, montant réellement
     * déboursé en CAD et compte d'encaisse payeur. Un paiement s'annule, il ne se supprime pas.
     */
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->integer('amount');
            $table->integer('amount_cad');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index('paid_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
