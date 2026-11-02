<?php

namespace App\Livewire\Stats;

use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use App\Support\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Statistiques de ventes (SalesReport) par mois d'une année, comparées à l'année précédente. Page ouverte à tous :
 *
 * - Un vendeur voit ses propres ventes.
 * - Avec sales.view_store, on voit les ventes de son magasin, ou celles d'un de ses vendeurs au choix.
 * - Avec stores.all en plus, on choisit aussi le magasin (vide : tous), puis un vendeur de ce magasin.
 */
class Index extends Component
{
    #[Url]
    public int $year = 0;

    /** Magasin affiché (vide : tous), offert seulement avec stores.all. */
    #[Url(as: 'magasin')]
    public string $storeFilter = '';

    /** Vendeur affiché (vide : tout le magasin), offert seulement avec sales.view_store. */
    #[Url(as: 'vendeur')]
    public string $sellerFilter = '';

    public function mount(): void
    {
        if ($this->year === 0) {
            $this->year = CarbonImmutable::today()->year;
        }
    }

    public function updatedStoreFilter(): void
    {
        $this->sellerFilter = '';
    }

    private function storeId(User $user): ?int
    {
        if (! $user->hasAllStores()) {
            return $user->store_id;
        }

        return $this->storeFilter !== '' ? (int) $this->storeFilter : null;
    }

    /**
     * Ce que la page montre à l'usager : un vendeur (lui-même ou celui choisi) ou un magasin (vide : tous), ou rien.
     *
     * @param  Collection<int, User>  $sellers  vendeurs offerts à l'usager (lui seul s'il n'a pas sales.view_store)
     * @return array{seller: ?User, storeId: ?int, label: string}|null
     */
    private function subject(User $user, Collection $sellers): ?array
    {
        if (! $user->can('sales.view_store')) {
            return $sellers->isNotEmpty() ? ['seller' => $user, 'storeId' => null, 'label' => __('Mes ventes')] : null;
        }

        $storeId = $this->storeId($user);
        $seller = $this->sellerFilter !== '' ? $sellers->find((int) $this->sellerFilter) : null;
        $storeName = $storeId === null ? __('Tous les magasins') : (string) Store::query()->whereKey($storeId)->value('name');

        if ($seller !== null) {
            return ['seller' => $seller, 'storeId' => $storeId, 'label' => $seller->firstname.' '.$seller->lastname.' — '.$storeName];
        }

        if ($storeId === null && ! $user->hasAllStores()) {
            return null;
        }

        return ['seller' => null, 'storeId' => $storeId, 'label' => __('Ventes du magasin').' — '.$storeName];
    }

    /**
     * @param  array{seller: ?User, storeId: ?int, label: string}  $subject
     */
    private function sales(array $subject, CarbonImmutable $from, CarbonImmutable $to): Money
    {
        return $subject['seller'] !== null
            ? SalesReport::forSalesperson($subject['seller']->id, $from, $to, $subject['storeId'])
            : SalesReport::forStore($subject['storeId'], $from, $to);
    }

    /**
     * Ventes de chaque mois de l'année jusqu'au mois en cours, et du même mois l'année précédente.
     *
     * @param  array{seller: ?User, storeId: ?int, label: string}  $subject
     * @return list<array{month: string, sales: Money, previous: Money}>
     */
    private function months(array $subject): array
    {
        $today = CarbonImmutable::today();
        $months = [];

        for ($month = CarbonImmutable::create($this->year); $month->year === $this->year && $month->lte($today); $month = $month->addMonth()) {
            $lastYear = $month->subYear();

            $months[] = [
                'month' => ucfirst($month->translatedFormat('F')),
                'sales' => $this->sales($subject, $month, $month->endOfMonth()->min($today)),
                'previous' => $this->sales($subject, $lastYear, $lastYear->endOfMonth()),
            ];
        }

        return $months;
    }

    /** @return list<int> */
    private function years(): array
    {
        $current = CarbonImmutable::today()->year;
        $first = min(SalesReport::firstSaleDate()->year ?? $current, $current);

        return range($current, $first);
    }

    public function render(): View
    {
        $user = auth()->user();
        $canViewStore = $user->can('sales.view_store');
        $sellers = $canViewStore
            ? SalesReport::salespeople($this->storeId($user))
            : SalesReport::salespeople()->where('id', $user->id);

        $subject = $this->subject($user, $sellers);
        $months = $subject === null ? [] : $this->months($subject);

        return view('livewire.stats.index', [
            'subject' => $subject,
            'months' => $months,
            'total' => Money::sum(array_column($months, 'sales')),
            'previousTotal' => Money::sum(array_column($months, 'previous')),
            'maxSales' => max([1, ...array_map(fn (array $row): int => $row['sales']->cents(), $months)]),
            'years' => $this->years(),
            'stores' => $canViewStore && $user->hasAllStores() ? Store::query()->orderBy('name')->get() : collect(),
            'sellers' => $canViewStore ? $sellers : collect(),
        ])->layout('layouts.app', ['title' => __('Statistiques')]);
    }
}
