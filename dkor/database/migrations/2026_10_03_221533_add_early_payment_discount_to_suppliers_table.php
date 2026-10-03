<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('early_payment_discount_percent', 5, 2)->default(0)->after('currency_id');
            $table->unsignedSmallInteger('early_payment_discount_days')->nullable()->after('early_payment_discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['early_payment_discount_percent', 'early_payment_discount_days']);
        });
    }
};
