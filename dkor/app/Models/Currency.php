<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property float $rate
 * @property bool $is_archived
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'rate', 'is_archived'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected $attributes = [
        'rate' => 1,
        'is_archived' => false,
    ];

    protected $casts = [
        'rate' => 'float',
        'is_archived' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (Currency $currency): void {
            if ($currency->wasChanged('rate')) {
                $currency->suppliers()->each(fn (Supplier $supplier) => $supplier->save());
            }
        });
    }

    /** @return HasMany<Supplier, $this> */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }
}
