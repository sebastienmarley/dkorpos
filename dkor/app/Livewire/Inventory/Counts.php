<?php

namespace App\Livewire\Inventory;

use App\Enums\InventoryCountStatus;
use App\Enums\StoreType;
use App\Models\InventoryCount;
use App\Models\Store;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Prises d'inventaire annuelles (issue #88), une par magasin à la fois (ADR 0012) : la liste, l'état de l'année
 * fiscale de chaque magasin et le démarrage d'une prise.
 */
class Counts extends Component
{
    use WithPagination;

    public bool $showStart = false;

    public string $countedOn = '';

    /** Magasin compté (ADR 0012), par défaut celui de l'usager. */
    public string $storeId = '';

    public function openStart(): void
    {
        $this->authorize('inventory.adjust');

        $this->countedOn = today()->toDateString();
        $this->storeId = (string) (Store::defaultIdFor(auth()->user()) ?? '');
        $this->resetValidation();
        $this->showStart = true;
    }

    public function start(): void
    {
        $this->authorize('inventory.adjust');

        $this->validate([
            'countedOn' => ['required', 'date'],
            'storeId' => ['required', 'integer', Rule::exists('stores', 'id')->where('type', StoreType::Physical->value)->whereIn('id', Store::query()->accessibleTo(auth()->user())->pluck('id')->all())],
        ]);

        try {
            $count = InventoryCount::start(Carbon::parse($this->countedOn), auth()->user(), (int) $this->storeId);
        } catch (DomainException $e) {
            $this->addError('countedOn', $e->getMessage());

            return;
        }

        $this->redirectRoute('inventory.counts.show', $count, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.inventory.counts', [
            'counts' => InventoryCount::query()->accessibleTo(auth()->user())->with(['starter', 'finalizer', 'store'])->withCount('lines')->latest('id')->paginate(15),
            'inProgress' => InventoryCount::query()->accessibleTo(auth()->user())->with('store')->where('status', InventoryCountStatus::InProgress)->orderBy('id')->get(),
            'fiscalYear' => InventoryCount::fiscalYearStatus()->filter(fn (array $row): bool => auth()->user()->canActForStore($row['store']->id))->values(),
            'fiscalYearStart' => InventoryCount::fiscalYearStart(),
            'stores' => Store::query()->accessibleTo(auth()->user())->where('type', StoreType::Physical)->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => __('Prises d\'inventaire')]);
    }
}
