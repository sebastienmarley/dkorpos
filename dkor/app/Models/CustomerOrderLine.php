<?php

namespace App\Models;

use App\Enums\CustomerOrderLineStatus;
use Database\Factories\CustomerOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_order_id
 * @property int|null $product_id
 * @property int|null $part_id Pièce de remplacement (non inventoriée), au lieu d'un produit.
 * @property int|null $service_id
 * @property int|null $supplier_id
 * @property string|null $description
 * @property bool $is_taxable
 * @property bool $is_custom
 * @property float|null $unit_cost Coût soumis par le fournisseur (article sur mesure).
 * @property string|null $quote_number Numéro de soumission du fournisseur (article sur mesure).
 * @property int|null $supplier_order_line_id
 * @property int|null $customer_order_pickup_id
 * @property int $quantity
 * @property int $quantity_reserved
 * @property int $quantity_on_order
 * @property float $unit_price
 * @property bool $is_no_charge Pièce remise sans frais (ex. : sous garantie) : prix vendant à 0 $.
 * @property float|null $cancellation_fee
 * @property string|null $note
 * @property CustomerOrderLineStatus $status
 * @property Carbon|null $delivered_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerOrder $order
 * @property-read Product|null $product
 * @property-read Part|null $part
 * @property-read Service|null $service
 * @property-read Supplier|null $supplier
 * @property-read string $label
 * @property-read SupplierOrderLine|null $supplierOrderLine
 * @property-read CustomerOrderPickup|null $pickup
 * @property-read Collection<int, DefectiveProduct> $defectiveProducts
 * @property-read float $total
 */
#[Fillable(['customer_order_id', 'product_id', 'part_id', 'service_id', 'supplier_id', 'description', 'supplier_order_line_id', 'customer_order_pickup_id', 'quantity', 'quantity_reserved', 'quantity_on_order', 'unit_price', 'is_no_charge', 'unit_cost', 'quote_number', 'is_taxable', 'is_custom', 'cancellation_fee', 'note', 'status', 'delivered_at', 'returned_at'])]
class CustomerOrderLine extends Model
{
    /** @use HasFactory<CustomerOrderLineFactory> */
    use HasFactory;

    protected $attributes = [
        'quantity_reserved' => 0,
        'quantity_on_order' => 0,
        'is_taxable' => true,
        'is_custom' => false,
        'is_no_charge' => false,
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_reserved' => 'integer',
        'quantity_on_order' => 'integer',
        'unit_price' => 'float',
        'is_no_charge' => 'boolean',
        'is_taxable' => 'boolean',
        'is_custom' => 'boolean',
        'unit_cost' => 'float',
        'cancellation_fee' => 'float',
        'status' => CustomerOrderLineStatus::class,
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    /** @return BelongsTo<CustomerOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Part, $this> */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Fournisseur qui rend le service (aucun pour un service interne).
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Caractéristiques de la vente à recopier sur une ligne séparée de celle-ci (ramassage, retour, annulation,
     * recommande…) : ce qui est vendu, sa description, son prix, son coût soumis et sa taxabilité.
     *
     * @return array<string, mixed>
     */
    public function saleAttributes(): array
    {
        return [
            'product_id' => $this->product_id,
            'part_id' => $this->part_id,
            'service_id' => $this->service_id,
            'supplier_id' => $this->supplier_id,
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'is_no_charge' => $this->is_no_charge,
            'unit_cost' => $this->unit_cost,
            'quote_number' => $this->quote_number,
            'is_taxable' => $this->is_taxable,
            'is_custom' => $this->is_custom,
            'note' => $this->note,
        ];
    }

    public function isService(): bool
    {
        return $this->service_id !== null;
    }

    /** Pièce de remplacement : commandée pour le client, jamais prise en stock ni inventoriée. */
    public function isPart(): bool
    {
        return $this->part_id !== null;
    }

    /** Nom affiché : modèle du produit ou de la pièce, ou nom du service. */
    public function getLabelAttribute(): string
    {
        return match (true) {
            $this->isService() => $this->service->name,
            $this->isPart() => $this->part->model,
            default => $this->product->model,
        };
    }

    /** @return BelongsTo<SupplierOrderLine, $this> */
    public function supplierOrderLine(): BelongsTo
    {
        return $this->belongsTo(SupplierOrderLine::class);
    }

    /** @return BelongsTo<CustomerOrderPickup, $this> */
    public function pickup(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderPickup::class, 'customer_order_pickup_id');
    }

    /** @return HasMany<DefectiveProduct, $this> */
    public function defectiveProducts(): HasMany
    {
        return $this->hasMany(DefectiveProduct::class);
    }

    public function getTotalAttribute(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }
}
