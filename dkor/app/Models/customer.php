<?php

namespace App\Models;

use App\Concerns\HasSearchName;
use Database\Factories\customerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string|null $search_name
 * @property string|null $phone
 * @property string|null $cellphone
 * @property string|null $email
 * @property float $credit_balance
 * @property string|null $address_civic
 * @property string|null $address_apartment
 * @property string|null $address_street
 * @property string|null $address_city
 * @property string|null $address_province
 * @property string|null $address_country
 * @property string|null $address_postal_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'firstname', 'lastname', 'phone', 'cellphone', 'email',
    'address_civic', 'address_apartment', 'address_street', 'address_city',
    'address_province', 'address_country', 'address_postal_code',
])]
class customer extends Model
{
    /** @use HasFactory<customerFactory> */
    use HasFactory;

    use HasSearchName;

    protected $casts = [
        'credit_balance' => 'float',
    ];

    /**
     * Clients dont le nom contient chacun des mots cherchés (peu importe l'ordre, la casse et les accents), ou dont
     * le courriel, le téléphone ou le cellulaire contient le texte cherché.
     *
     * @param  Builder<customer>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $term = trim($term);

        $query->where(function (Builder $query) use ($term): void {
            $query->where(fn (Builder $query) => $query->matchingName($term))
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%')
                ->orWhere('cellphone', 'like', '%'.$term.'%');
        });
    }
}
