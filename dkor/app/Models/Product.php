<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int|null $department_id
 * @property int|null $category_id
 * @property int|null $color_id
 * @property string $model
 * @property string $clean_model
 * @property string|null $supplier_model
 * @property float $cost
 * @property string|null $collection
 * @property string|null $description
 * @property float|null $length
 * @property float|null $width
 * @property float|null $height
 * @property float|null $weight
 * @property bool $is_discontinued
 * @property bool $is_non_orderable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Department|null $department
 * @property-read Category|null $category
 * @property-read Color|null $color
 * @property-read float $selling_price
 */
#[Fillable(['supplier_id', 'department_id', 'category_id', 'color_id', 'model', 'clean_model', 'supplier_model', 'collection', 'cost', 'description', 'length', 'width', 'height', 'weight', 'is_discontinued', 'is_non_orderable'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $casts = [
        'cost' => 'float',
        'length' => 'float',
        'width' => 'float',
        'height' => 'float',
        'weight' => 'float',
        'is_discontinued' => 'boolean',
        'is_non_orderable' => 'boolean',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    /** @return HasMany<InventoryUnit, $this> */
    public function inventoryUnits(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    /** @return HasOne<InventoryStock, $this> */
    public function inventoryStock(): HasOne
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function getSellingPriceAttribute(): float
    {
        return self::roundSellingPrice($this->cost * ($this->supplier->price_multiplier ?? 1.0));
    }

    public static function roundSellingPrice(float $price): float
    {
        if ($price < 20.0) {
            $whole = (int) floor($price);

            return ($whole + 0.99 >= $price) ? $whole + 0.99 : $whole + 1.99;
        }

        if ($price <= 100.0) {
            return (float) (int) ceil($price);
        }

        $base = (int) ceil($price);
        $lastDigit = $base % 10;

        return (float) ($lastDigit <= 4
            ? $base + (4 - $lastDigit)
            : $base + (9 - $lastDigit));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->model.($this->supplier_model ? ' ('.$this->supplier_model.')' : '');
    }
}
