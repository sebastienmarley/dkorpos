<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendeurs d'une commande client (maximum 3) et leur part de la vente en pourcentage.
     */
    public function up(): void
    {
        Schema::create('customer_order_salesperson', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->timestamps();

            $table->unique(['customer_order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_salesperson');
    }
};
