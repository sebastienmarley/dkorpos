<?php

namespace App\Models;

use App\Actions\QueuePriceChangeLabels;
use App\Enums\InventoryStatus;
use Database\Factories\InventoryStockFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $store_id Magasin (ADR 0012).
 * @property int $product_id
 * @property int $quantity_in_stock
 * @property int $quantity_on_order
 * @property int $quantity_in_demo
 * @property int $quantity_reserved
 * @property int $quantity_customer_order
 * @property int $quantity_in_delivery
 * @property int $quantity_defective_stock
 * @property int $quantity_defective_shipped
 * @property int $quantity_lost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 */
#[Fillable(['store_id', 'product_id', 'quantity_in_stock', 'quantity_on_order', 'quantity_in_demo', 'quantity_reserved', 'quantity_customer_order', 'quantity_in_delivery', 'quantity_defective_stock', 'quantity_defective_shipped', 'quantity_lost'])]
class InventoryStock extends Model
{
    /** @use HasFactory<InventoryStockFactory> */
    use HasFactory;

    protected $casts = [
        'quantity_in_stock' => 'integer',
        'quantity_on_order' => 'integer',
        'quantity_in_demo' => 'integer',
        'quantity_reserved' => 'integer',
        'quantity_customer_order' => 'integer',
        'quantity_in_delivery' => 'integer',
        'quantity_defective_stock' => 'integer',
        'quantity_defective_shipped' => 'integer',
        'quantity_lost' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (InventoryStock $stock): void {
            foreach (InventoryStatus::cases() as $status) {
                if ($stock->quantityFor($status) < 0) {
                    throw new DomainException(__('La quantité « :status » ne peut pas être négative.', ['status' => $status->label()]));
                }
            }
        });

        static::saved(function (InventoryStock $stock): void {
            if ($stock->wasChanged('quantity_in_demo') || ($stock->wasRecentlyCreated && $stock->quantity_in_demo > 0)) {
                app(QueuePriceChangeLabels::class)->forProducts([$stock->product_id]);
            }
        });
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function quantityFor(InventoryStatus $status): int
    {
        return (int) $this->{$status->column()};
    }

    /**
     * Les états sont exclusifs: seule la marchandise « en stock » est disponible à la vente.
     */
    public function quantityAvailable(): int
    {
        return $this->quantity_in_stock;
    }
}
