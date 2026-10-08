<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ramassage en magasin : les lignes remises au client à ce moment et le paiement exigé.
 *
 * @property int $id
 * @property int $customer_order_id
 * @property int|null $handled_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerOrder $order
 * @property-read User|null $handler
 * @property-read Collection<int, CustomerOrderLine> $lines
 * @property-read Collection<int, CustomerOrderPayment> $payments
 */
#[Fillable(['customer_order_id', 'handled_by'])]
class CustomerOrderPickup extends Model
{
    /** @return BelongsTo<CustomerOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @return HasMany<CustomerOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerOrderLine::class);
    }

    /** @return HasMany<CustomerOrderPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerOrderPayment::class);
    }
}
