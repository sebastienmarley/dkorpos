<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dossiers de produits défectueux rapportés par des clients : raison, photo, solution retenue (pièce de
     * remplacement commandée, remplacement du produit ou remboursement) pour la future gestion des défectueux.
     */
    public function up(): void
    {
        Schema::create('defective_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('resolution');
            $table->string('status')->default('open');
            $table->text('reason')->nullable();
            $table->string('replacement_part')->nullable();
            $table->foreignId('supplier_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'resolution']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defective_products');
    }
};
