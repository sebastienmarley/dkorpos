<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Magasin de la commande client (celui de l'employé qui la crée); les commandes existantes reçoivent le magasin
     * de leur créateur.
     */
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        DB::table('customer_orders')->whereNotNull('created_by')->orderBy('id')->each(function (object $order): void {
            DB::table('customer_orders')->where('id', $order->id)->update([
                'store_id' => DB::table('users')->where('id', $order->created_by)->value('store_id'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
