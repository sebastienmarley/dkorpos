<?php

namespace App\Livewire\Accounting;

use App\Enums\LedgerAccountType;
use App\Models\InventoryAdjustment;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Grand livre (ADR 0011) : les écritures, filtrables par compte, et les comptes, dont le comptable ajuste le code et
 * le nom à son plan comptable. Il peut ajouter ses propres comptes (frais bancaires, salaires…) pour les écritures
 * manuelles (ADR 0016).
 */
class Ledger extends Component
{
    use WithPagination;

    #[Url(as: 'account')]
    public string $accountFilter = '';

    public bool $showAccount = false;

    public ?int $editingAccountId = null;

    public string $code = '';

    public string $name = '';

    public string $type = '';

    public function updatedAccountFilter(): void
    {
        $this->resetPage();
    }

    public function createAccount(): void
    {
        $this->authorize('ledger.edit');

        $this->editingAccountId = null;
        $this->code = '';
        $this->name = '';
        $this->type = LedgerAccountType::Expense->value;
        $this->resetValidation();
        $this->showAccount = true;
    }

    public function editAccount(LedgerAccount $account): void
    {
        $this->authorize('ledger.edit');

        $this->editingAccountId = $account->id;
        $this->code = $account->code;
        $this->name = $account->name;
        $this->resetValidation();
        $this->showAccount = true;
    }

    public function saveAccount(): void
    {
        $this->authorize('ledger.edit');

        $account = $this->editingAccountId !== null ? LedgerAccount::findOrFail($this->editingAccountId) : null;

        $this->code = trim($this->code);
        $this->name = trim($this->name);

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('ledger_accounts', 'code')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => [$account === null ? 'required' : 'nullable', Rule::enum(LedgerAccountType::class)],
        ]);

        if ($account !== null) {
            $account->update(['code' => $validated['code'], 'name' => $validated['name']]);
        } else {
            LedgerAccount::create($validated);
        }

        $this->showAccount = false;

        Flux::toast(text: $account !== null ? __('Compte modifié.') : __('Compte ajouté.'), variant: 'success');
    }

    public function render(): View
    {
        $entries = LedgerEntry::query()
            ->with([
                'lines.account', 'user',
                'reference' => fn (Relation $relation) => $relation instanceof MorphTo
                    ? $relation->morphWith([InventoryAdjustment::class => ['product']])
                    : $relation,
            ])
            ->when(filled($this->accountFilter), fn (Builder $query) => $query->whereHas('lines', fn (Builder $lines) => $lines->where('ledger_account_id', $this->accountFilter)))
            ->latest('posted_on')
            ->latest('id')
            ->paginate(15);

        return view('livewire.accounting.ledger', [
            'entries' => $entries,
            'accounts' => LedgerAccount::query()->orderBy('code')->get(),
            'types' => LedgerAccountType::cases(),
        ])->layout('layouts.app', ['title' => __('Grand livre')]);
    }
}
