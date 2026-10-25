<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Rules\UniqueCleanProductModel;
use App\Support\Money;
use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Pièce de remplacement commandée pour un client (non inventoriée), liée aux produits qu'elle répare.
 * Unique chez son fournisseur par son modèle nettoyé.
 *
 * @property int $id
 * @property int $supplier_id
 * @property string $model
 * @property string $clean_model
 * @property string $description
 * @property Money $last_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Money $selling_price
 */
#[Fillable(['supplier_id', 'model', 'description', 'last_cost'])]
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory;

    protected $attributes = [
        'last_cost' => 0,
    ];

    protected $casts = [
        'last_cost' => MoneyCast::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (Part $part): void {
            $part->clean_model = UniqueCleanProductModel::clean($part->model);
        });
    }

    /**
     * Pièces dont le modèle, la description ou un produit lié (modèle ou modèle fournisseur) contient le terme.
     * Le modèle est comparé une fois nettoyé : « AB-12 » trouve « ab12 ».
     *
     * @param  Builder<Part>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $term = trim($term);
        $cleanTerm = UniqueCleanProductModel::clean($term);

        $query->where(function (Builder $query) use ($term, $cleanTerm): void {
            $query->whereLike('model', '%'.$term.'%')
                ->orWhereLike('description', '%'.$term.'%')
                ->when($cleanTerm !== '', fn (Builder $query) => $query->orWhereLike('clean_model', '%'.$cleanTerm.'%'))
                ->orWhereHas('products', fn (Builder $query) => $query
                    ->whereLike('model', '%'.$term.'%')
                    ->orWhereLike('supplier_model', '%'.$term.'%'));
        });
    }

    /**
     * Prix de vente calculé : dernier coût × multiplicateur du fournisseur, arrondi comme les produits. Une pièce
     * qui ne coûte rien reste à 0 $.
     */
    public function getSellingPriceAttribute(): Money
    {
        if (! $this->last_cost->isPositive()) {
            return Money::zero();
        }

        return Product::sellingPriceFor($this->last_cost, $this->supplier->price_multiplier ?? 1.0);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }
}
