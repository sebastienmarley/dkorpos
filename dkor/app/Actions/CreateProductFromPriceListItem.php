<?php

namespace App\Actions;

use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Crée un produit à partir d'une ligne d'une liste de prix active, sauf s'il existe déjà chez le fournisseur
 * (même modèle fournisseur nettoyé), auquel cas le produit existant est retourné sans être modifié.
 */
class CreateProductFromPriceListItem
{
    public function handle(PriceListItem $item): Product
    {
        $item->loadMissing('priceListList.priceList');

        $list = $item->priceListList;
        $supplierId = $list->priceList->supplier_id;

        if (! PriceListItem::query()->inActiveLists($supplierId)->whereKey($item->id)->exists()) {
            throw new InvalidArgumentException(__('Cette ligne n\'appartient pas à une liste de prix active.'));
        }

        return DB::transaction(function () use ($item, $list, $supplierId): Product {
            $existing = Product::query()
                ->where('supplier_id', $supplierId)
                ->where(fn ($query) => $query
                    ->where('supplier_clean_model', $item->clean_model)
                    ->orWhere('clean_model', $item->clean_model))
                ->first();

            if ($existing !== null) {
                $item->update(['product_id' => $existing->id]);

                return $existing;
            }

            $product = Product::create([
                'supplier_id' => $supplierId,
                'model' => $item->model,
                'clean_model' => $item->clean_model,
                'supplier_model' => $item->model,
                'cost' => max(0.01, round(($item->cost ?? 0.01) * (1 - $list->discount_percent / 100), 2)),
                'imap' => $item->imap,
                'collection' => $item->collection,
                'description' => $item->description,
                'length' => $item->length,
                'width' => $item->width,
                'height' => $item->height,
                'weight' => $item->weight,
            ]);

            if ($item->upc !== null) {
                $product->upcs()->firstOrCreate(['upc' => $item->upc]);
            }

            $item->update(['product_id' => $product->id]);

            return $product;
        });
    }
}
