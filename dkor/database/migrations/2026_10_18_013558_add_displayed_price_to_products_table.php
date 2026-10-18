<?php

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prix imprimé sur l'étiquette du démonstrateur (en cents). Les démonstrateurs actuels sont présumés bien étiquetés :
     * on y reporte leur prix de vente actuel pour ne pas générer d'étiquettes pour tout le plancher au déploiement.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('displayed_price')->nullable()->after('cost');
        });

        Product::query()
            ->whereHas('inventoryStock', fn ($query) => $query->where('quantity_in_demo', '>', 0))
            ->with('supplier')
            ->chunkById(200, function (Collection $products): void {
                foreach ($products as $product) {
                    $product->displayed_price = $product->selling_price;
                    $product->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('displayed_price');
        });
    }
};
