<?php

namespace App\Models;

use App\Rules\UniqueCleanProductModel;
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
 * @property float $last_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read float $selling_price
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
        'last_cost' => 'float',
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
            $query->where('model', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')
                ->when($cleanTerm !== '', fn (Builder $query) => $query->orWhere('clean_model', 'like', '%'.$cleanTerm.'%'))
                ->orWhereHas('products', fn (Builder $query) => $query
                    ->where('model', 'like', '%'.$term.'%')
                    ->orWhere('supplier_model', 'like', '%'.$term.'%'));
        });
    }

    /**
     * Prix de vente calculé : dernier coût × multiplicateur du fournisseur, arrondi comme les produits. Une pièce
     * qui ne coûte rien reste à 0 $.
     */
    public function getSellingPriceAttribute(): float
    {
        if ($this->last_cost <= 0) {
            return 0.0;
        }

        return Product::roundSellingPrice($this->last_cost * ($this->supplier->price_multiplier ?? 1.0));
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
