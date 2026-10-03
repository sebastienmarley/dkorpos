<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->unsignedInteger('quantity_in_delivery')->default(0)->after('quantity_customer_order');
            $table->unsignedInteger('quantity_defective_stock')->default(0)->after('quantity_in_delivery');
            $table->unsignedInteger('quantity_defective_shipped')->default(0)->after('quantity_defective_stock');
            $table->unsignedInteger('quantity_lost')->default(0)->after('quantity_defective_shipped');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropColumn(['quantity_in_delivery', 'quantity_defective_stock', 'quantity_defective_shipped', 'quantity_lost']);
        });
    }
};
