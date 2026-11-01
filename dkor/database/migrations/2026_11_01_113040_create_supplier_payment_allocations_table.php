<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part d'un paiement appliquée à une facture : montant payé et escompte de paiement rapide (devise du fournisseur),
     * et montant soldé aux comptes fournisseurs en CAD, au taux de la facture.
     */
    public function up(): void
    {
        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $table->integer('amount');
            $table->integer('discount')->default(0);
            $table->integer('payable_cad');
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'supplier_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
    }
};
