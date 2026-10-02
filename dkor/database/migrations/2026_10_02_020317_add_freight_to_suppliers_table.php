<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('prepaid_amount', 10, 2)->nullable()->default(0)->after('shipping_fee');
            $table->boolean('collect')->default(false)->after('prepaid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['prepaid_amount', 'collect']);
        });
    }
};
