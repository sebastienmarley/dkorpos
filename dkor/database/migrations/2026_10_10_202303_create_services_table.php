<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogue des services vendus (installation, livraison, main-d'œuvre…). Un service interne est rendu par le
     * magasin (prix sur le service); un service externe est offert par un ou plusieurs fournisseurs (prix par
     * fournisseur, voir service_supplier). La description modèle évite de retaper la description à chaque vente.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description_template')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
