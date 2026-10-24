<?php

namespace App\Actions;

use App\Enums\InventoryStatus;
use App\Enums\LabelReason;
use App\Models\LabelRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tient la file d'étiquettes à jour pour les démonstrateurs : un produit en démo dont le prix de vente diffère du prix
 * imprimé sur son étiquette (ou qui n'en a jamais eu) reçoit une demande automatique, d'une étiquette par unité en démo.
 * Une demande automatique devenue inutile (prix revenu, plus en démo) est retirée; les demandes manuelles ne sont pas touchées.
 */
class QueuePriceChangeLabels
{
    /**
     * @param  list<int>  $productIds
     */
    public function forProducts(array $productIds): void
    {
        $this->sync(Product::query()->whereKey($productIds));
    }

    public function forSupplier(int $supplierId): void
    {
        $this->sync(Product::query()->where('supplier_id', $supplierId));
    }

    public function all(): void
    {
        $this->sync(Product::query());
    }

    /**
     * Raison automatique pour laquelle le produit a besoin d'une nouvelle étiquette, ou null s'il n'en a pas besoin.
     */
    public static function reasonFor(Product $product): ?LabelReason
    {
        if ($product->totalQuantity(InventoryStatus::InDemo) < 1) {
            return null;
        }

        if ($product->displayed_price === null) {
            return LabelReason::NewDemo;
        }

        return $product->displayed_price->equals($product->selling_price) ? null : LabelReason::PriceChange;
    }

    /**
     * @param  Builder<Product>  $products
     */
    private function sync(Builder $products): void
    {
        $products
            ->where(fn (Builder $query) => $query
                ->whereHas('inventoryStocks', fn (Builder $stock) => $stock->where('quantity_in_demo', '>', 0))
                ->orWhereHas('labelRequests', fn (Builder $request) => $request->pending()->whereIn('reason', LabelReason::automatic())))
            ->with(['supplier', 'inventoryStocks'])
            ->chunkById(200, function (Collection $chunk): void {
                $pending = LabelRequest::query()
                    ->pending()
                    ->whereIn('product_id', $chunk->modelKeys())
                    ->whereIn('reason', LabelReason::automatic())
                    ->get()
                    ->keyBy('product_id');

                foreach ($chunk as $product) {
                    $this->syncProduct($product, $pending->get($product->id));
                }
            });
    }

    private function syncProduct(Product $product, ?LabelRequest $pending): void
    {
        $reason = self::reasonFor($product);

        if ($reason === null) {
            $pending?->delete();

            return;
        }

        $quantity = $product->totalQuantity(InventoryStatus::InDemo);

        if ($pending !== null && $pending->reason === $reason) {
            $pending->update(['quantity' => $quantity]);

            return;
        }

        $pending?->delete();

        LabelRequest::create(['product_id' => $product->id, 'quantity' => $quantity, 'reason' => $reason]);
    }
}
