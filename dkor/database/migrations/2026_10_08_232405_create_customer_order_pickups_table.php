<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ramassages en magasin : chaque ramassage regroupe les lignes remises au client à ce moment.
     */
    public function up(): void
    {
        Schema::create('customer_order_pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->foreignId('customer_order_pickup_id')->nullable()->after('supplier_order_line_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_order_pickup_id');
        });

        Schema::dropIfExists('customer_order_pickups');
    }
};
