<?php

namespace App\Models;

use App\Enums\CustomerOrderLineStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Quantité d'une ligne de commande prévue dans une livraison. La ligne n'est séparée qu'à sa remise au client.
 *
 * @property int $id
 * @property int $customer_order_delivery_id
 * @property int $customer_order_line_id
 * @property int $quantity
 * @property CustomerOrderLineStatus|null $loaded_from_status Statut de la ligne avant son chargement : il lui revient si le client la refuse.
 * @property int|null $accepted_quantity Quantité acceptée par le client à la remise (le reste est refusé).
 * @property string|null $refusal_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerOrderDelivery $delivery
 * @property-read CustomerOrderLine $line
 */
#[Fillable(['customer_order_delivery_id', 'customer_order_line_id', 'quantity', 'loaded_from_status', 'accepted_quantity', 'refusal_reason'])]
class CustomerOrderDeliveryLine extends Model
{
    protected $casts = [
        'quantity' => 'integer',
        'accepted_quantity' => 'integer',
        'loaded_from_status' => CustomerOrderLineStatus::class,
    ];

    /** @return BelongsTo<CustomerOrderDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderDelivery::class, 'customer_order_delivery_id');
    }

    /** @return BelongsTo<CustomerOrderLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderLine::class, 'customer_order_line_id');
    }
}
