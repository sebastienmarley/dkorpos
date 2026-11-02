<?php

namespace App\Support;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Models\StoreTaxRegistration;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * États du grand livre sur une période (#131, ADR 0016) : balance de vérification, grand livre d'un compte et taxes à
 * remettre, pour un magasin ou pour l'ensemble. Les soldes sont en débits moins crédits.
 */
class LedgerReport
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public ?int $storeId = null,
    ) {}

    /**
     * Balance de vérification : par compte mouvementé, solde d'ouverture, débits et crédits de la période, solde de
     * clôture.
     *
     * @return Collection<int, array{account: LedgerAccount, opening: Money, debit: Money, credit: Money, closing: Money}>
     */
    public function trialBalance(): Collection
    {
        $opening = $this->lines()->whereHas('entry', fn (Builder $entry) => $entry->where('posted_on', '<', $this->from->toDateString()))
            ->groupBy('ledger_account_id')
            ->selectRaw('ledger_account_id, sum(debit) - sum(credit) as balance')
            ->pluck('balance', 'ledger_account_id');

        $period = $this->lines()->whereHas('entry', fn (Builder $entry) => $this->inPeriod($entry))
            ->groupBy('ledger_account_id')
            ->selectRaw('ledger_account_id, sum(debit) as debits, sum(credit) as credits')
            ->get()
            ->keyBy('ledger_account_id');

        return LedgerAccount::query()
            ->whereIn('id', $opening->keys()->merge($period->keys())->unique())
            ->orderBy('code')
            ->get()
            ->map(function (LedgerAccount $account) use ($opening, $period): array {
                $openingBalance = Money::of((int) ($opening[$account->id] ?? 0));
                $debit = Money::of((int) ($period[$account->id]->debits ?? 0));
                $credit = Money::of((int) ($period[$account->id]->credits ?? 0));

                return [
                    'account' => $account,
                    'opening' => $openingBalance,
                    'debit' => $debit,
                    'credit' => $credit,
                    'closing' => $openingBalance->plus($debit)->minus($credit),
                ];
            });
    }

    /**
     * Grand livre d'un compte : solde d'ouverture, lignes de la période avec le solde courant, solde de clôture.
     *
     * @return array{opening: Money, lines: Collection<int, array{line: LedgerEntryLine, balance: Money}>, closing: Money}
     */
    public function accountLedger(LedgerAccount $account): array
    {
        $opening = Money::of((int) $this->lines()
            ->where('ledger_account_id', $account->id)
            ->whereHas('entry', fn (Builder $entry) => $entry->where('posted_on', '<', $this->from->toDateString()))
            ->selectRaw('coalesce(sum(debit) - sum(credit), 0) as balance')
            ->value('balance'));

        $balance = $opening;
        $lines = collect();

        $periodLines = $this->lines()
            ->where('ledger_account_id', $account->id)
            ->whereHas('entry', fn (Builder $entry) => $this->inPeriod($entry))
            ->with(['entry.reference', 'entry.user'])
            ->get()
            ->sortBy(fn (LedgerEntryLine $line): string => $line->entry->posted_on->toDateString().'-'.Str::padLeft((string) $line->ledger_entry_id, 12, '0'));

        foreach ($periodLines as $line) {
            $balance = $balance->plus($line->debit)->minus($line->credit);
            $lines->push(['line' => $line, 'balance' => $balance]);
        }

        return ['opening' => $opening, 'lines' => $lines, 'closing' => $balance];
    }

    /**
     * Taxes de la période, par taxe : perçues (crédits nets au compte « à payer »), payées aux fournisseurs (débits
     * nets au compte « à recevoir ») et net à remettre (positif) ou à recevoir (négatif), avec les numéros
     * d'inscription des magasins.
     *
     * @return Collection<string, array{collected: Money, paid: Money, net: Money, registrations: list<string>}>
     */
    public function taxes(): Collection
    {
        $balances = $this->trialBalance()->filter(fn (array $row): bool => Str::startsWith((string) $row['account']->system_key, [LedgerAccount::TAX_PAYABLE_PREFIX, LedgerAccount::TAX_RECEIVABLE_PREFIX]));
        $taxes = [];

        foreach ($balances as $row) {
            $key = (string) $row['account']->system_key;
            $isPayable = Str::startsWith($key, LedgerAccount::TAX_PAYABLE_PREFIX);
            $name = Str::after($key, $isPayable ? LedgerAccount::TAX_PAYABLE_PREFIX : LedgerAccount::TAX_RECEIVABLE_PREFIX);
            $taxes[$name] ??= ['collected' => Money::zero(), 'paid' => Money::zero()];
            $taxes[$name][$isPayable ? 'collected' : 'paid'] = $isPayable ? $row['credit']->minus($row['debit']) : $row['debit']->minus($row['credit']);
        }

        ksort($taxes);

        return collect($taxes)->map(fn (array $tax, string $name): array => [
            ...$tax,
            'net' => $tax['collected']->minus($tax['paid']),
            'registrations' => array_values(StoreTaxRegistration::query()
                ->where('tax_name', $name)
                ->when($this->storeId !== null, fn (Builder $query) => $query->where('store_id', $this->storeId))
                ->orderBy('number')
                ->get()
                ->map(fn (StoreTaxRegistration $registration): string => $registration->number)
                ->unique()
                ->all()),
        ]);
    }

    /**
     * Lignes des écritures du magasin choisi (ou de toutes).
     *
     * @return Builder<LedgerEntryLine>
     */
    private function lines(): Builder
    {
        return LedgerEntryLine::query()
            ->when($this->storeId !== null, fn (Builder $query) => $query->whereHas('entry', fn (Builder $entry) => $entry->where('store_id', $this->storeId)));
    }

    /**
     * @param  Builder<LedgerEntry>  $entry
     * @return Builder<LedgerEntry>
     */
    private function inPeriod(Builder $entry): Builder
    {
        return $entry->whereBetween('posted_on', [$this->from->toDateString(), $this->to->toDateString()]);
    }
}
