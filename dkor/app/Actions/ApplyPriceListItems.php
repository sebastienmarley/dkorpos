<?php

namespace App\Actions;

use App\Models\PriceListItem;
use App\Models\PriceListList;
use App\Models\Product;
use App\Models\ProductUpc;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applique le contenu d'une liste aux produits du fournisseur, par lots de 200 lignes.
 *
 * Le produit est retrouvé par son modèle fournisseur nettoyé. Il reçoit le coût (moins l'escompte de la liste), l'IMAP,
 * la collection, la description et les dimensions ; son UPC est ajouté à sa liste d'UPC sans jamais en retirer.
 * Les cellules vides ne remplacent jamais une valeur existante du produit.
 */
class ApplyPriceListItems
{
    public const BATCH_SIZE = 200;

    /**
     * @return int nombre de produits mis à jour
     */
    public function handle(PriceListList $list): int
    {
        $supplierId = $list->priceList->supplier_id;
        $matched = 0;

        $list->items()->chunkById(self::BATCH_SIZE, function (Collection $items) use ($list, $supplierId, &$matched): void {
            DB::transaction(function () use ($items, $list, $supplierId, &$matched): void {
                $products = Product::query()
                    ->where('supplier_id', $supplierId)
                    ->whereIn('supplier_clean_model', $items->pluck('clean_model')->unique()->all())
                    ->get()
                    ->keyBy('supplier_clean_model');

                $now = now();
                $upcs = [];

                foreach ($items as $item) {
                    $product = $products->get($item->clean_model);

                    if ($product === null) {
                        continue;
                    }

                    $this->updateProduct($product, $item, $list->discount_percent);
                    $item->update(['product_id' => $product->id]);
                    $matched++;

                    if ($item->upc !== null) {
                        $upcs[] = ['product_id' => $product->id, 'upc' => $item->upc, 'created_at' => $now, 'updated_at' => $now];
                    }
                }

                ProductUpc::query()->insertOrIgnore($upcs);
            });
        });

        $list->update(['applied_at' => now()]);

        return $matched;
    }

    private function updateProduct(Product $product, PriceListItem $item, float $discountPercent): void
    {
        $changes = array_filter(
            [
                'imap' => $item->imap,
                'collection' => $item->collection,
                'description' => $item->description,
                'length' => $item->length,
                'width' => $item->width,
                'height' => $item->height,
                'weight' => $item->weight,
            ],
            fn (mixed $value): bool => $value !== null,
        );

        if ($item->cost !== null && $item->cost > 0) {
            $changes['cost'] = max(0.01, round($item->cost * (1 - $discountPercent / 100), 2));
        }

        $product->update($changes);
    }
}
