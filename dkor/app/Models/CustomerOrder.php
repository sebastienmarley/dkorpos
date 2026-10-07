<?php

namespace App\Models;

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use Database\Factories\CustomerOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $customer_id
 * @property CustomerOrderStatus $status
 * @property float $balance_due
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read customer $customer
 * @property-read User|null $creator
 * @property-read Collection<int, User> $salespeople
 * @property-read Collection<int, CustomerOrderLine> $lines
 */
#[Fillable(['customer_id', 'status', 'balance_due', 'created_by'])]
class CustomerOrder extends Model
{
    /** @use HasFactory<CustomerOrderFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
        'balance_due' => 0,
    ];

    protected $casts = [
        'status' => CustomerOrderStatus::class,
        'balance_due' => 'float',
    ];

    /** @return BelongsTo<customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Vendeurs de la commande, avec leur part de la vente (pivot `percent`).
     *
     * @return BelongsToMany<User, $this>
     */
    public function salespeople(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_order_salesperson')
            ->withPivot('percent')
            ->withTimestamps();
    }

    /** @return HasMany<CustomerOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerOrderLine::class);
    }

    /**
     * Ajoute une unité du produit à la commande : la quantité de sa ligne modifiable est augmentée,
     * sinon une ligne est créée au prix vendant. Le statut dépend de la quantité disponible en stock.
     */
    public function addProduct(Product $product): CustomerOrderLine
    {
        return DB::transaction(function () use ($product): CustomerOrderLine {
            $line = $this->lines()
                ->where('product_id', $product->id)
                ->whereIn('status', [CustomerOrderLineStatus::InStock, CustomerOrderLineStatus::OnOrder])
                ->lockForUpdate()
                ->first();

            $quantity = ($line->quantity ?? 0) + 1;
            $status = ($product->inventoryStock?->quantityAvailable() ?? 0) >= $quantity
                ? CustomerOrderLineStatus::InStock
                : CustomerOrderLineStatus::OnOrder;

            if ($line === null) {
                $line = $this->lines()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->selling_price,
                    'status' => $status,
                ]);
            } else {
                $line->update(['quantity' => $quantity, 'status' => $status]);
            }

            $this->recalculateBalance();

            return $line;
        });
    }

    public function recalculateBalance(): void
    {
        $this->update([
            'balance_due' => round($this->lines()->get()
                ->filter(fn (CustomerOrderLine $line): bool => $line->status->isBillable())
                ->sum(fn (CustomerOrderLine $line): float => $line->total), 2),
        ]);
    }

    /**
     * Répartition de la vente entre les vendeurs, dans l'ordre où ils ont été enregistrés.
     *
     * @return array<int, array{user_id: int, percent: int}>
     */
    public function salespeopleShares(): array
    {
        return $this->salespeople()->newPivotQuery()
            ->orderBy('id')
            ->get(['user_id', 'percent'])
            ->map(fn (object $row): array => ['user_id' => (int) $row->user_id, 'percent' => (int) $row->percent])
            ->all();
    }

    /**
     * @param  array<int, array{user_id: int, percent: int}>  $salespeople
     */
    public function syncSalespeople(array $salespeople): void
    {
        $this->salespeople()->sync(collect($salespeople)->mapWithKeys(
            fn (array $row): array => [$row['user_id'] => ['percent' => $row['percent']]],
        )->all());
    }
}
