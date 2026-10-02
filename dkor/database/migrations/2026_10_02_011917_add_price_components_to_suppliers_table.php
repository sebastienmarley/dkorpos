<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('base_multiplier', 8, 4)->default(2)->after('order_email');
            $table->decimal('exchange_rate', 8, 4)->default(0)->after('base_multiplier');
            $table->decimal('customs_fee', 8, 4)->default(0)->after('exchange_rate');
            $table->decimal('shipping_fee', 8, 4)->default(0)->after('customs_fee');
        });

        DB::table('suppliers')->update(['base_multiplier' => DB::raw('price_multiplier')]);
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['base_multiplier', 'exchange_rate', 'customs_fee', 'shipping_fee']);
        });
    }
};
