<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->boolean('is_drop_ship')->default(false)->after('shipping_supplier_id');
            $table->string('drop_ship_name')->nullable()->after('is_drop_ship');
            $table->string('drop_ship_address_civic', 20)->nullable()->after('drop_ship_name');
            $table->string('drop_ship_address_apartment', 20)->nullable()->after('drop_ship_address_civic');
            $table->string('drop_ship_address_street')->nullable()->after('drop_ship_address_apartment');
            $table->string('drop_ship_address_city', 100)->nullable()->after('drop_ship_address_street');
            $table->string('drop_ship_address_province', 2)->nullable()->after('drop_ship_address_city');
            $table->string('drop_ship_address_country', 2)->nullable()->after('drop_ship_address_province');
            $table->string('drop_ship_address_postal_code', 6)->nullable()->after('drop_ship_address_country');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropColumn([
                'is_drop_ship', 'drop_ship_name', 'drop_ship_address_civic', 'drop_ship_address_apartment',
                'drop_ship_address_street', 'drop_ship_address_city', 'drop_ship_address_province',
                'drop_ship_address_country', 'drop_ship_address_postal_code',
            ]);
        });
    }
};
