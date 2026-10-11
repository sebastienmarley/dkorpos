<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Offre d'un fournisseur pour un service : son coût et le prix vendant au client.
 *
 * @property int $id
 * @property int $service_id
 * @property int $supplier_id
 * @property Money $cost
 * @property Money $selling_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServiceSupplier extends Pivot
{
    protected $table = 'service_supplier';

    public $incrementing = true;

    protected $casts = [
        'cost' => MoneyCast::class,
        'selling_price' => MoneyCast::class,
    ];
}
