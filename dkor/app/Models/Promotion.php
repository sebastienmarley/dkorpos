<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PromotionType;
use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Promotion temporaire sur des produits (issue #84). Elle baisse le prix des lignes admissibles d'une commande : une
 * seule promotion par commande, jamais sur un produit déjà escompté (prix sous son prix de vente).
 *
 * @property int $id
 * @property string $name
 * @property PromotionType $type
 * @property float|null $percent Pourcentage de rabais (type Percent).
 * @property Money|null $amount Rabais total (type FixedAmount).
 * @property int|null $minimum_quantity Nombre d'articles admissibles à acheter (type FixedAmount).
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PromotionTarget> $targets
 */
#[Fillable(['name', 'type', 'percent', 'amount', 'minimum_quantity', 'starts_on', 'ends_on', 'is_active'])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'type' => PromotionType::class,
        'percent' => 'float',
        'amount' => MoneyCast::class,
        'minimum_quantity' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<PromotionTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    /** Rabais en quelques mots : « 10 % », « 100,00 $ à l'achat de 2 », « Taxes payées ». */
    public function discountLabel(): string
    {
        return match ($this->type) {
            PromotionType::Percent => rtrim(rtrim(number_format((float) $this->percent, 2, ',', ' '), '0'), ',').' %',
            PromotionType::FixedAmount => __(':amount à l\'achat de :quantity', ['amount' => ($this->amount ?? Money::zero())->format(), 'quantity' => (int) $this->minimum_quantity]),
            PromotionType::TaxesPaid => __('Taxes payées'),
        };
    }

    /** La promotion est en vigueur à cette date (active, début et fin inclus). */
    public function isInEffectOn(CarbonInterface $date): bool
    {
        return $this->is_active && $date->toDateString() >= $this->starts_on->toDateString() && $date->toDateString() <= $this->ends_on->toDateString();
    }

    /**
     * Promotions actives en vigueur à cette date (début et fin inclus).
     *
     * @param  Builder<Promotion>  $query
     */
    #[Scope]
    protected function inEffectOn(Builder $query, CarbonInterface $date): void
    {
        $query->where('is_active', true)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date);
    }

    /**
     * Le produit fait partie de la promotion : lui-même, son fournisseur, sa catégorie ou son département est ciblé.
     */
    public function covers(Product $product): bool
    {
        $departmentId = $product->category?->department_id;

        return $this->targets->contains(fn (PromotionTarget $target): bool => match ($target->target_type) {
            'product' => $target->target_id === $product->id,
            'supplier' => $target->target_id === $product->supplier_id,
            'category' => $target->target_id === $product->category_id,
            'department' => $target->target_id === $departmentId,
            default => false,
        });
    }

    /**
     * Lignes de la commande que la promotion peut escompter : un produit couvert, pas encore remis au client, à son
     * prix de vente ou plus (un produit déjà escompté n'est pas touché) et taxable pour « nous payons les taxes ».
     *
     * @return Collection<int, CustomerOrderLine>
     */
    public function eligibleLinesOf(CustomerOrder $order): Collection
    {
        $this->loadMissing('targets');

        return $order->lines()->with('product.category', 'product.supplier')->orderBy('id')->get()->filter(
            fn (CustomerOrderLine $line): bool => $line->product !== null
                && ! $line->is_custom
                && $line->promotion_id === null
                && ! $line->isMarkedDown()
                && $line->status->isPickable()
                && $line->unit_price->greaterThanOrEqual($line->product->selling_price)
                && ($this->type !== PromotionType::TaxesPaid || $line->is_taxable)
                && $this->covers($line->product),
        )->values();
    }

    /**
     * La commande remplit les conditions de la promotion : au moins une ligne admissible (le nombre d'articles requis
     * pour un montant fixe), et aucune promotion déjà appliquée.
     */
    public function isApplicableTo(CustomerOrder $order): bool
    {
        if ($order->promotion_id !== null) {
            return false;
        }

        $quantity = $this->eligibleLinesOf($order)->sum('quantity');

        return $quantity > 0 && ($this->type !== PromotionType::FixedAmount || $quantity >= (int) $this->minimum_quantity);
    }

    /**
     * Nouveau prix unitaire de chaque ligne admissible.
     *
     * @param  Collection<int, CustomerOrderLine>  $lines
     * @return array<int, Money> prix unitaire par ligne
     */
    public function discountedPrices(CustomerOrder $order, Collection $lines): array
    {
        return match ($this->type) {
            PromotionType::Percent => $lines->mapWithKeys(fn (CustomerOrderLine $line): array => [
                $line->id => $line->unit_price->minus($line->unit_price->percent((float) $this->percent)),
            ])->all(),
            PromotionType::TaxesPaid => $lines->mapWithKeys(fn (CustomerOrderLine $line): array => [
                $line->id => $this->priceBeforeTaxes($order, $line->unit_price),
            ])->all(),
            PromotionType::FixedAmount => $this->fixedAmountPrices($lines),
        };
    }

    /**
     * Prix dont le total, taxes de la commande incluses, revient au prix d'origine (ex. : 114,98 $ → 100 $).
     */
    private function priceBeforeTaxes(CustomerOrder $order, Money $price): Money
    {
        $taxed = $order->taxesFor($price)['total'];

        if ($taxed->isZero()) {
            return $price;
        }

        return Money::of((int) round($price->cents() * $price->cents() / $taxed->cents()));
    }

    /**
     * Répartit le rabais sur les lignes au prorata de leur total. La part d'une ligne de plusieurs articles est
     * arrondie à un multiple de sa quantité (un seul prix unitaire) ; les cents qui restent vont à une ligne d'un
     * seul article, s'il y en a une. Le rabais ne dépasse jamais le total des lignes.
     *
     * @param  Collection<int, CustomerOrderLine>  $lines
     * @return array<int, Money>
     */
    private function fixedAmountPrices(Collection $lines): array
    {
        $total = Money::sum($lines->map(fn (CustomerOrderLine $line): Money => $line->total)->all());
        $discount = ($this->amount ?? Money::zero())->min($total);

        if ($discount->isZero()) {
            return $lines->mapWithKeys(fn (CustomerOrderLine $line): array => [$line->id => $line->unit_price])->all();
        }

        $shares = $discount->allocate($lines->mapWithKeys(fn (CustomerOrderLine $line): array => [$line->id => $line->total->cents()])->all());
        $perUnit = [];
        $leftover = 0;

        foreach ($lines as $line) {
            $unitShare = intdiv($shares[$line->id]->cents(), $line->quantity);
            $leftover += $shares[$line->id]->cents() - $unitShare * $line->quantity;
            $perUnit[$line->id] = $unitShare;
        }

        $single = $lines->first(fn (CustomerOrderLine $line): bool => $line->quantity === 1 && $line->unit_price->cents() - $perUnit[$line->id] >= $leftover);

        if ($single !== null) {
            $perUnit[$single->id] += $leftover;
        }

        return $lines->mapWithKeys(fn (CustomerOrderLine $line): array => [
            $line->id => $line->unit_price->minus(Money::of($perUnit[$line->id])),
        ])->all();
    }
}
