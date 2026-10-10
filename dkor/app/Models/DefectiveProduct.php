<?php

namespace App\Models;

use App\Enums\DefectiveResolution;
use App\Enums\DefectiveStatus;
use Database\Factories\DefectiveProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dossier d'un produit défectueux rapporté par un client.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $customer_order_id
 * @property int|null $customer_order_line_id
 * @property int $quantity
 * @property DefectiveResolution $resolution
 * @property DefectiveStatus $status
 * @property string|null $reason
 * @property string|null $replacement_part
 * @property int|null $supplier_order_line_id
 * @property string|null $photo_path
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read CustomerOrder|null $customerOrder
 * @property-read CustomerOrderLine|null $customerOrderLine
 * @property-read SupplierOrderLine|null $supplierOrderLine
 * @property-read User|null $creator
 */
#[Fillable(['product_id', 'customer_order_id', 'customer_order_line_id', 'quantity', 'resolution', 'status', 'reason', 'replacement_part', 'supplier_order_line_id', 'photo_path', 'created_by'])]
class DefectiveProduct extends Model
{
    /** @use HasFactory<DefectiveProductFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'open',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'resolution' => DefectiveResolution::class,
        'status' => DefectiveStatus::class,
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<CustomerOrder, $this> */
    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    /** @return BelongsTo<CustomerOrderLine, $this> */
    public function customerOrderLine(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderLine::class);
    }

    /** @return BelongsTo<SupplierOrderLine, $this> */
    public function supplierOrderLine(): BelongsTo
    {
        return $this->belongsTo(SupplierOrderLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
