<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->foreignId('product_demo_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_clearance_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_clearance_id');
            $table->dropConstrainedForeignId('product_demo_id');
        });
    }
};
