<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Ce que couvre une promotion : un département, une catégorie, un fournisseur ou un produit.
 *
 * @property int $id
 * @property int $promotion_id
 * @property string $target_type
 * @property int $target_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Promotion $promotion
 * @property-read Department|Category|Supplier|Product|null $target
 */
#[Fillable(['promotion_id', 'target_type', 'target_id'])]
class PromotionTarget extends Model
{
    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** Ce que la cible désigne, pour l'affichage : « Département Éclairage », « Produit WRF555 »… */
    public function label(): string
    {
        $kind = match ($this->target_type) {
            'department' => __('Département'),
            'category' => __('Catégorie'),
            'supplier' => __('Fournisseur'),
            'product' => __('Produit'),
            default => $this->target_type,
        };

        $name = match (true) {
            $this->target instanceof Product => $this->target->model,
            $this->target instanceof Department, $this->target instanceof Category, $this->target instanceof Supplier => $this->target->name,
            default => '—',
        };

        return "{$kind} {$name}";
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
