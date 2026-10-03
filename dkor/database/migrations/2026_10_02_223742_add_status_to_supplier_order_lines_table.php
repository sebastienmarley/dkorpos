<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_order_lines', function (Blueprint $table) {
            $table->string('status')->default('active')->after('quantity_received');
            $table->string('cancellation_reason')->nullable()->after('status');
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_order_lines', function (Blueprint $table) {
            $table->dropColumn(['status', 'cancellation_reason', 'cancellation_requested_at', 'cancelled_at']);
        });
    }
};
