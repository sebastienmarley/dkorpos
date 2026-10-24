<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Emplacements de l'entrepôt (issue #96) : chaque magasin a ses zones (déjà utilisées par la prise d'inventaire)
     * et, dans chaque zone, ses emplacements. L'emplacement informe : il est noté sur une ligne de commande client
     * (où chercher l'article réservé) et comme emplacement habituel d'un produit dans un magasin. Il ne tient pas de
     * quantités. Les zones existantes sont rattachées au premier magasin physique.
     */
    public function up(): void
    {
        Schema::table('inventory_zones', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('inventory_zones', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->unique(['store_id', 'name']);
        });

        $storeId = DB::table('stores')->where('type', 'physical')->orderBy('id')->value('id');

        if ($storeId !== null) {
            DB::table('inventory_zones')->whereNull('store_id')->update(['store_id' => $storeId]);
        }

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_zone_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->foreignId('inventory_location_id')->nullable()->after('customer_order_pickup_id')->constrained()->nullOnDelete();
        });

        Schema::create('product_store_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_store_locations');

        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_location_id');
        });

        Schema::dropIfExists('inventory_locations');

        Schema::table('inventory_zones', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'name']);
            $table->dropConstrainedForeignId('store_id');
            $table->unique('name');
        });
    }
};
