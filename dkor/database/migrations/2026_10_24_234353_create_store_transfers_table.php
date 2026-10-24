<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transferts entre magasins (issue #103, ADR 0012) : quatre états d'inventaire (en transfert, en transit, client en
     * transfert, client en transit) et les transferts eux-mêmes, demandés par le magasin qui reçoit, pour son stock ou
     * pour une ligne de commande client.
     */
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->integer('quantity_in_transfer')->default(0);
            $table->integer('quantity_in_transit')->default(0);
            $table->integer('quantity_customer_in_transfer')->default(0);
            $table->integer('quantity_customer_in_transit')->default(0);
        });

        Schema::create('store_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('to_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_received')->nullable();
            $table->string('source_status');
            $table->string('status');
            $table->string('refusal_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('requested_at');
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('accepted_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['from_store_id', 'status']);
            $table->index(['to_store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_transfers');

        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropColumn(['quantity_in_transfer', 'quantity_in_transit', 'quantity_customer_in_transfer', 'quantity_customer_in_transit']);
        });
    }
};
