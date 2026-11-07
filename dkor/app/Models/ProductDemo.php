<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CustomerOrderLineStatus;
use App\Support\Money;
use Database\Factories\ProductDemoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Fiche de démonstrateur (ADR 0018) : le prix réduit, la note et la mention « tel quel » d'un article du stock en démo
 * d'un magasin. Aucune unité n'est suivie : la vente réserve un article du stock en démo et sa sortie suit le FIFO.
 *
 * @property int $id
 * @property int $product_id
 * @property int $store_id Magasin du stock.
 * @property Money $price
 * @property string|null $note État de l'article (ex. : « égratignure côté gauche »).
 * @property bool $is_as_is Vendu tel quel : ne se retourne pas (une défectuosité passe par le dossier défectueux).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Store $store
 * @property-read Collection<int, CustomerOrderLine> $lines
 */
#[Fillable(['product_id', 'store_id', 'price', 'note', 'is_as_is'])]
class ProductDemo extends Model
{
    /** @use HasFactory<ProductDemoFactory> */
    use HasFactory;

    protected $attributes = [
        'is_as_is' => false,
    ];

    protected $casts = [
        'price' => MoneyCast::class,
        'is_as_is' => 'boolean',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<CustomerOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerOrderLine::class);
    }

    /**
     * Fiches qu'aucune ligne de commande non annulée ne référence : elles peuvent être vendues ou retirées.
     *
     * @param  Builder<ProductDemo>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->whereDoesntHave('lines', fn (Builder $line) => $line->where('status', '!=', CustomerOrderLineStatus::Cancelled));
    }

    /** Une ligne de commande non annulée référence la fiche : réservée, vendue ou retournée. */
    public function isTaken(): bool
    {
        return $this->lines()->where('status', '!=', CustomerOrderLineStatus::Cancelled)->exists();
    }
}
