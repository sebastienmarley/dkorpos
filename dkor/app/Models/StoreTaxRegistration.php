<?php

namespace App\Models;

use Database\Factories\StoreTaxRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Numéro de taxe du marchand (affiché sur les factures clients) pour une taxe donnée, identifiée par son nom.
 *
 * @property int $id
 * @property int $store_id
 * @property string $tax_name
 * @property string $number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['store_id', 'tax_name', 'number'])]
class StoreTaxRegistration extends Model
{
    /** @use HasFactory<StoreTaxRegistrationFactory> */
    use HasFactory;

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
