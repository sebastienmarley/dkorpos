<?php

namespace App\Models;

use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\LedgerEntryFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Écriture du grand livre (ADR 0011) : équilibrée (débits = crédits), liée à son document source, immuable. Une
 * correction passe une écriture inverse. Seul post() crée une écriture.
 *
 * @property int $id
 * @property int|null $store_id Magasin (ADR 0012).
 * @property Carbon $posted_on
 * @property string $description
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property-read Collection<int, LedgerEntryLine> $lines
 * @property-read User|null $user
 * @property-read Store|null $store
 * @property-read Model|null $reference
 */
#[Fillable(['store_id', 'posted_on', 'description', 'reference_type', 'reference_id', 'user_id'])]
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $casts = [
        'posted_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Une écriture du grand livre ne se modifie pas.'));
        static::deleting(fn () => throw new LogicException('Une écriture du grand livre ne se supprime pas.'));
    }

    /**
     * Passe une écriture équilibrée. Chaque ligne a un débit ou un crédit positif (pas les deux). Une écriture
     * d'inventaire porte son magasin (ADR 0012); les autres, tenues par le bureau chef, n'en ont pas. Une écriture
     * datée dans une période clôturée est reportée au premier jour ouvert, avec la date du document dans sa
     * description (ADR 0016).
     *
     * @param  list<array{account: LedgerAccount, debit?: Money, credit?: Money}>  $lines
     *
     * @throws DomainException si l'écriture est vide, déséquilibrée ou si une ligne est invalide.
     */
    public static function post(string $description, array $lines, ?Model $reference = null, ?CarbonInterface $postedOn = null, int|string|null $userId = null, ?int $storeId = null): self
    {
        if (count($lines) < 2) {
            throw new DomainException(__('Une écriture a au moins deux lignes.'));
        }

        $debits = Money::zero();
        $credits = Money::zero();

        foreach ($lines as $line) {
            $debit = $line['debit'] ?? Money::zero();
            $credit = $line['credit'] ?? Money::zero();

            if ($debit->isNegative() || $credit->isNegative() || $debit->isZero() === $credit->isZero()) {
                throw new DomainException(__('Chaque ligne d\'une écriture a soit un débit, soit un crédit, positif.'));
            }

            $debits = $debits->plus($debit);
            $credits = $credits->plus($credit);
        }

        if (! $debits->equals($credits)) {
            throw new DomainException(__('L\'écriture n\'est pas équilibrée (débits :debits $, crédits :credits $).', [
                'debits' => $debits->toDecimal(),
                'credits' => $credits->toDecimal(),
            ]));
        }

        $documentDate = $postedOn ?? today();
        $postedOn = LedgerClosing::openDateFor($documentDate);

        if (! $postedOn->isSameDay($documentDate)) {
            $description = __(':description (document du :date)', ['description' => $description, 'date' => $documentDate->toDateString()]);
        }

        return DB::transaction(function () use ($description, $lines, $reference, $postedOn, $userId, $storeId): self {
            $entry = static::create([
                'store_id' => $storeId,
                'posted_on' => $postedOn,
                'description' => $description,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $userId ?? auth()->id(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'ledger_account_id' => $line['account']->id,
                    'debit' => $line['debit'] ?? Money::zero(),
                    'credit' => $line['credit'] ?? Money::zero(),
                ]);
            }

            return $entry;
        });
    }

    /**
     * Lignes d'écriture à partir de montants signés (positif : débit, négatif : crédit), regroupées par compte. Un
     * compte dont le montant net est nul ne donne pas de ligne.
     *
     * @param  list<array{0: LedgerAccount, 1: Money}>  $amounts
     * @return list<array{account: LedgerAccount, debit?: Money, credit?: Money}>
     */
    public static function signedLines(array $amounts): array
    {
        $accounts = [];
        $totals = [];

        foreach ($amounts as [$account, $amount]) {
            $accounts[$account->id] = $account;
            $totals[$account->id] = ($totals[$account->id] ?? Money::zero())->plus($amount);
        }

        $lines = [];

        foreach ($totals as $accountId => $total) {
            if (! $total->isZero()) {
                $lines[] = $total->isPositive()
                    ? ['account' => $accounts[$accountId], 'debit' => $total]
                    : ['account' => $accounts[$accountId], 'credit' => $total->negate()];
            }
        }

        return $lines;
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<LedgerEntryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(LedgerEntryLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Lien vers le document source de l'écriture, s'il a une page.
     */
    public function sourceUrl(): ?string
    {
        return match (true) {
            $this->reference instanceof SupplierInvoice => route('accounting.invoices.show', $this->reference),
            $this->reference instanceof Reception => route('receptions.show', $this->reference),
            $this->reference instanceof CustomerOrder => route('customer-orders.show', $this->reference),
            $this->reference instanceof CustomerOrderPayment => route('customer-orders.show', $this->reference->customer_order_id),
            $this->reference instanceof SupplierPayment => route('accounting.payables', ['supplier' => $this->reference->supplier_id]),
            $this->reference instanceof InventoryAdjustment => route('inventory.adjustments'),
            $this->reference instanceof JournalEntry => route('accounting.journal'),
            default => null,
        };
    }

    public function total(): Money
    {
        return Money::sum($this->lines->map(fn (LedgerEntryLine $line): Money => $line->debit));
    }
}
