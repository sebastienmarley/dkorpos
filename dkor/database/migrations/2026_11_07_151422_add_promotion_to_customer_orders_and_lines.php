<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('regular_unit_price')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('regular_unit_price');
        });

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
        });
    }
};
