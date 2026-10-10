<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Numéros de taxe du marchand par magasin et par nom de taxe (TPS, TVQ, TVH…). Rattachés au nom et non à un
     * taux : ils survivent à un changement de taux.
     */
    public function up(): void
    {
        Schema::create('store_tax_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('tax_name', 50);
            $table->string('number');
            $table->timestamps();

            $table->unique(['store_id', 'tax_name']);
        });

        foreach (['gst_number' => 'TPS', 'qst_number' => 'TVQ'] as $column => $taxName) {
            DB::table('stores')->whereNotNull($column)->where($column, '!=', '')->get(['id', $column])
                ->each(fn (object $store) => DB::table('store_tax_registrations')->insert([
                    'store_id' => $store->id,
                    'tax_name' => $taxName,
                    'number' => $store->{$column},
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['gst_number', 'qst_number']);
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('gst_number')->nullable()->after('address_postal_code');
            $table->string('qst_number')->nullable()->after('gst_number');
        });

        foreach (['gst_number' => 'TPS', 'qst_number' => 'TVQ'] as $column => $taxName) {
            DB::table('store_tax_registrations')->where('tax_name', $taxName)->get()
                ->each(fn (object $registration) => DB::table('stores')->where('id', $registration->store_id)->update([$column => $registration->number]));
        }

        Schema::dropIfExists('store_tax_registrations');
    }
};
