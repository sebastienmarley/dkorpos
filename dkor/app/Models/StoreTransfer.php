<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use App\Enums\StoreTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Transfert de marchandise d'un magasin à un autre (issue #103, ADR 0012), demandé par le magasin qui reçoit, pour son
 * stock ou pour une ligne de commande client. Les unités FIFO restent dans le magasin qui expédie jusqu'à la
 * réception : « En transfert », puis « En transit » une fois accepté (« Client en transfert », puis « Client en
 * transit » pour un client).
 *
 * @property int $id
 * @property int $from_store_id Magasin qui expédie.
 * @property int $to_store_id Magasin qui reçoit (et qui demande).
 * @property int $product_id
 * @property int|null $customer_order_line_id Ligne de commande client servie par ce transfert.
 * @property int $quantity
 * @property int|null $quantity_received
 * @property InventoryStatus $source_status État de départ : en stock, ou en démo (dernière unité, pour un client).
 * @property StoreTransferStatus $status
 * @property string|null $refusal_reason
 * @property int|null $requested_by
 * @property Carbon $requested_at
 * @property int|null $accepted_by
 * @property Carbon|null $accepted_at
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property int|null $closed_by Usager qui a refusé ou annulé le transfert.
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Store $fromStore
 * @property-read Store $toStore
 * @property-read Product $product
 * @property-read CustomerOrderLine|null $line
 * @property-read User|null $requester
 */
#[Fillable([
    'from_store_id', 'to_store_id', 'product_id', 'customer_order_line_id', 'quantity', 'quantity_received', 'source_status',
    'status', 'refusal_reason', 'requested_by', 'requested_at', 'accepted_by', 'accepted_at', 'received_by', 'received_at',
    'closed_by', 'closed_at',
])]
class StoreTransfer extends Model
{
    protected $casts = [
        'quantity' => 'integer',
        'quantity_received' => 'integer',
        'source_status' => InventoryStatus::class,
        'status' => StoreTransferStatus::class,
        'requested_at' => 'datetime',
        'accepted_at' => 'datetime',
        'received_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<Store, $this> */
    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<CustomerOrderLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderLine::class, 'customer_order_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isForCustomer(): bool
    {
        return $this->customer_order_line_id !== null;
    }

    /** État de la marchandise demandée, chez le magasin qui expédie. */
    public function requestedStatus(): InventoryStatus
    {
        return $this->isForCustomer() ? InventoryStatus::CustomerInTransfer : InventoryStatus::InTransfer;
    }

    /** État de la marchandise acceptée, en route vers le magasin qui reçoit. */
    public function transitStatus(): InventoryStatus
    {
        return $this->isForCustomer() ? InventoryStatus::CustomerInTransit : InventoryStatus::InTransit;
    }

    /** État d'arrivée chez le magasin qui reçoit. */
    public function arrivalStatus(): InventoryStatus
    {
        return $this->isForCustomer() ? InventoryStatus::ReservedCustomer : InventoryStatus::InStock;
    }

    /**
     * Transferts encore ouverts (demandés ou en transit).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', [StoreTransferStatus::Requested, StoreTransferStatus::InTransit]);
    }
}
