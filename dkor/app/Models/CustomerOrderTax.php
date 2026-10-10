<?php

namespace App\Models;

use Database\Factories\CustomerOrderTaxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Taxe figée d'une commande client : nom, taux et numéro du marchand copiés à la création de la commande, pour que
 * les totaux ne changent pas quand les taux sont modifiés plus tard.
 *
 * @property int $id
 * @property int $customer_order_id
 * @property int|null $tax_id
 * @property string $name
 * @property float $rate Pourcentage (ex. 9.975).
 * @property bool $is_compound En cascade : calculée sur le montant plus les taxes individuelles.
 * @property string|null $registration_number Numéro de taxe du marchand au moment de la commande.
 * @property float $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerOrder $order
 */
#[Fillable(['customer_order_id', 'tax_id', 'name', 'rate', 'is_compound', 'registration_number', 'amount'])]
class CustomerOrderTax extends Model
{
    /** @use HasFactory<CustomerOrderTaxFactory> */
    use HasFactory;

    protected $casts = [
        'rate' => 'float',
        'is_compound' => 'boolean',
        'amount' => 'float',
    ];

    /** @return BelongsTo<CustomerOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    /**
     * Numéro du marchand à imprimer sur la facture : celui copié à la création, sinon celui que le magasin a
     * saisi depuis.
     */
    public function registrationNumber(): ?string
    {
        return $this->registration_number
            ?? $this->order->store?->taxRegistrations()->where('tax_name', $this->name)->value('number');
    }
}
