<?php

namespace App\Models;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerOrderDelivery $delivery
 * @property-read CustomerOrderLine $line
 */
#[Fillable(['customer_order_delivery_id', 'customer_order_line_id', 'quantity'])]
class CustomerOrderDeliveryLine extends Model
{
    protected $casts = [
        'quantity' => 'integer',
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
