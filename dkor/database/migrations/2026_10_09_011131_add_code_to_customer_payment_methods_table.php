<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Code des modes de paiement gérés par l'application (non modifiables dans l'interface). Le mode « Comptant »
     * (code `cash`) est créé, ou repris s'il existe déjà sous ce nom.
     */
    public function up(): void
    {
        Schema::table('customer_payment_methods', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
        });

        $existing = DB::table('customer_payment_methods')->whereRaw('lower(name) = ?', ['comptant'])->orderBy('id')->first();

        if ($existing !== null) {
            DB::table('customer_payment_methods')->where('id', $existing->id)->update(['code' => 'cash', 'name' => 'Comptant', 'is_active' => true]);

            return;
        }

        DB::table('customer_payment_methods')->insert([
            'code' => 'cash',
            'name' => 'Comptant',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('customer_payment_methods', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
