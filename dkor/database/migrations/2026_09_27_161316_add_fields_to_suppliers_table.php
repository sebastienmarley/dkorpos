<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->string('account_number')->nullable()->after('email');
            $table->string('bank_account')->nullable()->after('account_number');
            $table->string('payment_address')->nullable()->after('bank_account');
            $table->string('order_email')->nullable()->after('payment_address');
            $table->boolean('orderable')->default(true)->after('order_email');
            $table->boolean('is_active')->default(true)->after('orderable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['email', 'account_number', 'bank_account', 'payment_address', 'order_email', 'orderable', 'is_active']);
        });
    }
};
