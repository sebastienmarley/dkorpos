<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fournisseurs qui offrent un service externe, avec leur coût et le prix vendant au client.
     */
    public function up(): void
    {
        Schema::create('service_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['service_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_supplier');
    }
};
