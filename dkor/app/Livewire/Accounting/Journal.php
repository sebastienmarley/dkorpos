<?php

namespace App\Livewire\Accounting;

use App\Actions\RecordJournalEntry;
use App\Actions\ReverseJournalEntry;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Store;
use App\Support\Money;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Journal général (ADR 0016) : les écritures manuelles, pour ce qui n'a pas de document dans l'application (soldes
 * d'ouverture, dépôts des processeurs de paiement, virements, frais bancaires, paie), et leur renversement.
 */
class Journal extends Component
{
    use WithPagination;

    public bool $showEntry = false;

    public string $entryDate = '';

    public string $description = '';

    public string $documentNumber = '';

    public string $storeId = '';

    /** @var list<array{account_id: string, debit: string, credit: string}> */
    public array $lines = [];

    public function openEntry(): void
    {
        $this->authorize('ledger.journal');

        $this->resetValidation();
        $this->entryDate = today()->toDateString();
        $this->description = '';
        $this->documentNumber = '';
        $this->storeId = '';
        $this->lines = [$this->blankLine(), $this->blankLine()];
        $this->showEntry = true;
    }

    public function addLine(): void
    {
        $this->lines = [...$this->lines, $this->blankLine()];
    }

    public function removeLine(int $index): void
    {
        $this->lines = array_values(array_filter($this->lines, fn (int $key): bool => $key !== $index, ARRAY_FILTER_USE_KEY));
    }

    public function save(): void
    {
        $this->authorize('ledger.journal');

        $this->validate([
            'entryDate' => ['required', 'date'],
            'description' => ['required', 'string', 'max:200'],
            'documentNumber' => ['nullable', 'string', 'max:100'],
            'storeId' => ['nullable', 'integer', 'exists:stores,id'],
            'lines.*.account_id' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $lines = [];

        foreach ($this->lines as $line) {
            $debit = Money::tryParse($line['debit']) ?? Money::zero();
            $credit = Money::tryParse($line['credit']) ?? Money::zero();

            if ($debit->isZero() && $credit->isZero()) {
                continue;
            }

            if (blank($line['account_id'])) {
                $this->addError('lines', __('Choisissez le compte de chaque ligne.'));

                return;
            }

            $lines[] = ['account' => LedgerAccount::query()->findOrFail((int) $line['account_id']), 'debit' => $debit, 'credit' => $credit];
        }

        try {
            app(RecordJournalEntry::class)->handle(
                auth()->user(),
                Carbon::parse($this->entryDate),
                $this->description,
                $this->documentNumber,
                filled($this->storeId) ? (int) $this->storeId : null,
                $lines,
            );
        } catch (DomainException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        $this->showEntry = false;

        Flux::toast(text: __('Écriture passée.'), variant: 'success');
    }

    public function reverse(JournalEntry $entry): void
    {
        $this->authorize('ledger.journal');

        try {
            app(ReverseJournalEntry::class)->handle(auth()->user(), $entry, today());
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(text: __('Écriture renversée.'), variant: 'success');
    }

    /**
     * Totaux des débits et des crédits saisis, pour montrer l'équilibre pendant la saisie.
     *
     * @return array{debit: Money, credit: Money}
     */
    private function lineTotals(): array
    {
        return [
            'debit' => Money::sum(array_map(fn (array $line): Money => Money::tryParse($line['debit']) ?? Money::zero(), $this->lines)),
            'credit' => Money::sum(array_map(fn (array $line): Money => Money::tryParse($line['credit']) ?? Money::zero(), $this->lines)),
        ];
    }

    /**
     * @return array{account_id: string, debit: string, credit: string}
     */
    private function blankLine(): array
    {
        return ['account_id' => '', 'debit' => '', 'credit' => ''];
    }

    public function render(): View
    {
        return view('livewire.accounting.journal', [
            'entries' => JournalEntry::query()
                ->with(['ledgerEntry.lines.account', 'creator', 'store', 'reversal', 'reversed'])
                ->latest('entry_date')
                ->latest('id')
                ->paginate(15),
            'totals' => $this->lineTotals(),
            'accounts' => LedgerAccount::query()->orderBy('code')->get(),
            'stores' => Store::query()->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => __('Journal général')]);
    }
}
