<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('early_payment_next_month')->default(false)->after('early_payment_discount_days');
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->boolean('discount_next_month')->default(false)->after('discount_days');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn('discount_next_month');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('early_payment_next_month');
        });
    }
};
