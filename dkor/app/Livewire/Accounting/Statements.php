<?php

namespace App\Livewire\Accounting;

use App\Models\LedgerAccount;
use App\Models\LedgerClosing;
use App\Models\LedgerEntry;
use App\Models\Store;
use App\Support\LedgerReport;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * États comptables (#131, ADR 0016) : balance de vérification, grand livre d'un compte, taxes à remettre et clôture
 * de période, sur une période et pour un magasin ou l'ensemble; export CSV pour la comptable.
 */
class Statements extends Component
{
    public const TABS = ['balance', 'account', 'taxes', 'closing'];

    #[Url]
    public string $tab = 'balance';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'store')]
    public string $storeFilter = '';

    #[Url(as: 'account')]
    public string $accountId = '';

    public string $closeThrough = '';

    public string $reopenFrom = '';

    public function mount(): void
    {
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'balance';
        $this->from = $this->validDate($this->from) ?? today()->startOfMonth()->toDateString();
        $this->to = $this->validDate($this->to) ?? today()->endOfMonth()->toDateString();
        $this->closeThrough = today()->subMonthNoOverflow()->endOfMonth()->toDateString();
    }

    /**
     * Ouvre le grand livre d'un compte depuis la balance.
     */
    public function showAccount(int $accountId): void
    {
        $this->accountId = (string) $accountId;
        $this->tab = 'account';
    }

    public function exportBalance(): StreamedResponse
    {
        $rows = array_values($this->report()->trialBalance()->map(fn (array $row): array => [
            $row['account']->code,
            $row['account']->name,
            $this->decimal($row['opening']),
            $this->decimal($row['debit']),
            $this->decimal($row['credit']),
            $this->decimal($row['closing']),
        ])->all());

        return $this->csv('balance', [__('Code'), __('Compte'), __('Solde d\'ouverture'), __('Débits'), __('Crédits'), __('Solde de clôture')], $rows);
    }

    public function exportEntries(): StreamedResponse
    {
        $report = $this->report();
        $rows = [];

        LedgerEntry::query()
            ->with(['lines.account', 'store'])
            ->whereBetween('posted_on', [$report->from->toDateString(), $report->to->toDateString()])
            ->when($report->storeId !== null, fn (Builder $query) => $query->where('store_id', $report->storeId))
            ->orderBy('posted_on')
            ->orderBy('id')
            ->each(function (LedgerEntry $entry) use (&$rows): void {
                foreach ($entry->lines as $line) {
                    $rows[] = [
                        $entry->posted_on->toDateString(),
                        $entry->id,
                        $entry->description,
                        $entry->store_id === null ? '' : $entry->store->name,
                        $line->account->code,
                        $line->account->name,
                        $line->debit->isZero() ? '' : $this->decimal($line->debit),
                        $line->credit->isZero() ? '' : $this->decimal($line->credit),
                    ];
                }
            });

        return $this->csv('ecritures', [__('Date'), __('Écriture'), __('Description'), __('Magasin'), __('Code'), __('Compte'), __('Débit'), __('Crédit')], $rows);
    }

    public function closePeriod(): void
    {
        $this->authorize('ledger.close');

        $this->validate(['closeThrough' => ['required', 'date']]);

        try {
            LedgerClosing::close(Carbon::parse($this->closeThrough), auth()->user());
        } catch (DomainException $e) {
            $this->addError('closeThrough', $e->getMessage());

            return;
        }

        Flux::toast(text: __('Grand livre clôturé jusqu\'au :date.', ['date' => $this->closeThrough]), variant: 'success');
    }

    public function reopenPeriod(): void
    {
        $this->authorize('ledger.close');

        $this->validate(['reopenFrom' => ['required', 'date']]);

        try {
            LedgerClosing::reopenFrom(Carbon::parse($this->reopenFrom), auth()->user());
        } catch (DomainException $e) {
            $this->addError('reopenFrom', $e->getMessage());

            return;
        }

        $this->reopenFrom = '';

        Flux::toast(text: __('Période rouverte.'), variant: 'success');
    }

    private function report(): LedgerReport
    {
        $from = CarbonImmutable::parse($this->validDate($this->from) ?? today()->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($this->validDate($this->to) ?? $from->endOfMonth()->toDateString());

        return new LedgerReport($from, $to->max($from), filled($this->storeFilter) ? (int) $this->storeFilter : null);
    }

    private function validDate(string $value): ?string
    {
        return Carbon::canBeCreatedFromFormat($value, 'Y-m-d') ? $value : null;
    }

    private function decimal(Money $amount): string
    {
        return str_replace('.', ',', $amount->toDecimal());
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string|int>>  $rows
     */
    private function csv(string $name, array $header, array $rows): StreamedResponse
    {
        $report = $this->report();
        $filename = $name.'_'.$report->from->toDateString().'_'.$report->to->toDateString().'.csv';

        return response()->streamDownload(function () use ($header, $rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\u{FEFF}");
            fputcsv($output, $header, ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($output, $row, ';', '"', '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $report = $this->report();
        $account = $this->tab === 'account' && filled($this->accountId) ? LedgerAccount::query()->find((int) $this->accountId) : null;
        $balance = $this->tab === 'balance' ? $report->trialBalance() : collect();

        return view('livewire.accounting.statements', [
            'report' => $report,
            'balance' => $balance,
            'totals' => [
                'debit' => Money::sum($balance->pluck('debit')->all()),
                'credit' => Money::sum($balance->pluck('credit')->all()),
            ],
            'account' => $account,
            'accountLedger' => $account !== null ? $report->accountLedger($account) : null,
            'taxes' => $this->tab === 'taxes' ? $report->taxes() : collect(),
            'closedThrough' => LedgerClosing::closedThrough(),
            'closings' => $this->tab === 'closing' ? LedgerClosing::query()->with('user')->latest('id')->limit(20)->get() : collect(),
            'accounts' => LedgerAccount::query()->orderBy('code')->get(),
            'stores' => Store::query()->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => __('États comptables')]);
    }
}
