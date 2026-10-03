<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->boolean('is_collect')->default(false)->after('status');
            $table->foreignId('shipping_supplier_id')->nullable()->after('is_collect')
                ->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_supplier_id');
            $table->dropColumn('is_collect');
        });
    }
};
