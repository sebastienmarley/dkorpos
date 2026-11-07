<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('billing_store_id')->nullable()->constrained('stores')->nullOnDelete();
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->foreignId('billing_store_id')->nullable()->constrained('stores')->nullOnDelete();
        });

        DB::table('supplier_orders')->update(['billing_store_id' => DB::raw('store_id')]);
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_store_id');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_store_id');
        });
    }
};
