<?php

namespace App\Models;

use App\Actions\QueuePriceChangeLabels;
use App\Casts\MoneyCast;
use App\Rules\UniqueCleanProductModel;
use App\Support\Money;
use Database\Factories\ProductFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int|null $category_id
 * @property int|null $color_id
 * @property int|null $style_id
 * @property string $model
 * @property string $clean_model
 * @property string|null $supplier_model
 * @property string|null $supplier_clean_model
 * @property Money $cost
 * @property Money|null $imap
 * @property string|null $collection
 * @property string|null $description
 * @property float|null $length
 * @property float|null $width
 * @property float|null $height
 * @property float|null $weight
 * @property bool $is_discontinued
 * @property bool $is_non_orderable
 * @property bool $is_taxable
 * @property bool $is_custom Gabarit de produit sur mesure : chaque vente porte ses spécifications, son coût soumis et son prix.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Department|null $department
 * @property-read Category|null $category
 * @property-read Color|null $color
 * @property-read Style|null $style
 * @property Money|null $displayed_price
 * @property-read Money $selling_price
 */
#[Fillable(['supplier_id', 'category_id', 'color_id', 'style_id', 'model', 'clean_model', 'supplier_model', 'collection', 'cost', 'displayed_price', 'imap', 'description', 'length', 'width', 'height', 'weight', 'is_discontinued', 'is_non_orderable', 'is_taxable', 'is_custom'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $attributes = [
        'is_taxable' => true,
        'is_custom' => false,
    ];

    protected $casts = [
        'cost' => MoneyCast::class,
        'displayed_price' => MoneyCast::class,
        'imap' => MoneyCast::class,
        'length' => 'float',
        'width' => 'float',
        'height' => 'float',
        'weight' => 'float',
        'is_discontinued' => 'boolean',
        'is_non_orderable' => 'boolean',
        'is_taxable' => 'boolean',
        'is_custom' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (blank($product->supplier_model) && filled($product->model)) {
                $product->supplier_model = $product->model;
            }

            if ($product->isDirty('supplier_model') || blank($product->supplier_clean_model)) {
                $product->supplier_clean_model = filled($product->supplier_model)
                    ? UniqueCleanProductModel::clean($product->supplier_model)
                    : null;
            }
        });

        static::saved(function (Product $product): void {
            if ($product->wasChanged(['cost', 'supplier_id', 'displayed_price'])) {
                app(QueuePriceChangeLabels::class)->forProducts([$product->id]);
            }
        });
    }

    /** @return HasMany<ProductStoreLocation, $this> */
    public function storeLocations(): HasMany
    {
        return $this->hasMany(ProductStoreLocation::class);
    }

    /** Emplacement habituel du produit dans l'entrepôt de ce magasin, s'il est noté. */
    public function habitualLocationFor(int $storeId): ?InventoryLocation
    {
        return $this->storeLocations()->where('store_id', $storeId)->with('location.zone')->first()?->location;
    }

    /**
     * Note (ou efface, avec null) l'emplacement habituel du produit dans ce magasin.
     *
     * @throws DomainException si l'emplacement n'est pas un emplacement actif de ce magasin.
     */
    public function setHabitualLocation(int $storeId, ?int $locationId): void
    {
        if ($locationId === null) {
            $this->storeLocations()->where('store_id', $storeId)->delete();

            return;
        }

        if (! InventoryLocation::query()->ofStore($storeId)->selectable()->whereKey($locationId)->exists()) {
            throw new DomainException(__('Choisissez un emplacement actif de ce magasin.'));
        }

        $this->storeLocations()->updateOrCreate(['store_id' => $storeId], ['inventory_location_id' => $locationId]);
    }

    /**
     * Avertissement si ce produit dépasse les limites d'expédition du magasin (plus grande dimension, poids) ou si ses
     * dimensions sont inconnues; null s'il peut être expédié ou si le magasin n'a pas de limites.
     */
    public function shippingWarningFor(Store $store): ?string
    {
        $dimensions = array_filter([$this->length, $this->width, $this->height], fn (?float $value): bool => $value !== null && $value > 0);
        $checksLength = $store->shipping_max_length !== null;
        $checksWeight = $store->shipping_max_weight !== null;

        if (($checksLength && $dimensions === []) || ($checksWeight && ! $this->weight)) {
            return __('Dimensions inconnues : vérifiez que l\'article peut être expédié.');
        }

        if ($checksLength && max($dimensions) > $store->shipping_max_length) {
            return __('Trop grand pour l\'expédition (:size, maximum :max).', ['size' => max($dimensions), 'max' => $store->shipping_max_length]);
        }

        if ($checksWeight && $this->weight > $store->shipping_max_weight) {
            return __('Trop lourd pour l\'expédition (:weight, maximum :max).', ['weight' => $this->weight, 'max' => $store->shipping_max_weight]);
        }

        return null;
    }

    public function isInPriceList(): bool
    {
        return PriceListItem::query()
            ->where('clean_model', $this->supplier_clean_model)
            ->whereHas('priceListList.priceList', fn (Builder $query) => $query->where('supplier_id', $this->supplier_id))
            ->exists();
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Département déduit de la catégorie du produit.
     *
     * @return HasOneThrough<Department, Category, $this>
     */
    public function department(): HasOneThrough
    {
        return $this->hasOneThrough(Department::class, Category::class, 'id', 'id', 'category_id', 'department_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Color, $this> */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /** @return BelongsTo<Style, $this> */
    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    /** @return HasMany<InventoryUnit, $this> */
    public function inventoryUnits(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    /** @return HasMany<ProductUpc, $this> */
    public function upcs(): HasMany
    {
        return $this->hasMany(ProductUpc::class);
    }

    /** @return BelongsToMany<Part, $this> */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class)->withTimestamps();
    }

    /** @return HasMany<LabelRequest, $this> */
    public function labelRequests(): HasMany
    {
        return $this->hasMany(LabelRequest::class);
    }

    /** @return HasOne<InventoryStock, $this> */
    public function inventoryStock(): HasOne
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function getSellingPriceAttribute(): Money
    {
        return self::sellingPriceFor($this->cost, $this->supplier->price_multiplier ?? 1.0);
    }

    /**
     * Prix de vente d'un coût : coût × multiplicateur du fournisseur, arrondi au cent, puis à sa tranche (ADR 0008).
     */
    public static function sellingPriceFor(Money $cost, float|string $multiplier): Money
    {
        return self::roundSellingPrice($cost->multiply($multiplier));
    }

    /**
     * Tranches du prix de vente : x,99 $ sous 20 $, dollar supérieur jusqu'à 100 $, puis un prix qui finit par 4 ou 9.
     */
    public static function roundSellingPrice(Money $price): Money
    {
        $cents = $price->cents();

        if ($cents < 2000) {
            $candidate = intdiv($cents, 100) * 100 + 99;

            return Money::of($candidate >= $cents ? $candidate : $candidate + 100);
        }

        $dollars = intdiv($cents + 99, 100);

        if ($cents <= 10000) {
            return Money::of($dollars * 100);
        }

        $lastDigit = $dollars % 10;

        return Money::of(($dollars + ($lastDigit <= 4 ? 4 - $lastDigit : 9 - $lastDigit)) * 100);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->model.($this->supplier_model ? ' ('.$this->supplier_model.')' : '');
    }
}
