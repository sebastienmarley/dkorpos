<?php

namespace App\Livewire;

use App\Models\CustomerOrder;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use App\Support\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Ventes de l'accueil (SalesReport) : celles de l'usager s'il vend, celles du magasin avec sales.view_store (de tous
 * les magasins, ou d'un au choix, avec stores.all). Pour le jour, la semaine (du dimanche) et le mois en cours.
 */
class SalesStats extends Component
{
    /** Magasin affiché (vide : tous), offert seulement avec stores.all. */
    public string $storeFilter = '';

    /**
     * @return array<string, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function periods(): array
    {
        $today = CarbonImmutable::today();

        return [
            __('Aujourd\'hui') => [$today, $today],
            __('Cette semaine') => [$today->startOfWeek(CarbonImmutable::SUNDAY), $today],
            __('Ce mois-ci') => [$today->startOfMonth(), $today],
        ];
    }

    /**
     * @return array<string, Money>|null
     */
    private function ownSales(User $user): ?array
    {
        $sells = $user->hasRole('salesman') || CustomerOrder::query()->whereHas('salespeople', fn ($query) => $query->whereKey($user->id))->exists();

        if (! $sells) {
            return null;
        }

        return array_map(fn (array $period): Money => Money::of(SalesReport::bySalesperson(...$period)[$user->id] ?? 0), $this->periods());
    }

    /**
     * @return array{name: string, sales: array<string, Money>}|null
     */
    private function storeSales(User $user): ?array
    {
        if (! $user->can('sales.view_store')) {
            return null;
        }

        $storeId = match (true) {
            ! $user->hasAllStores() => $user->store_id,
            $this->storeFilter !== '' => (int) $this->storeFilter,
            default => null,
        };

        if ($storeId === null && ! $user->hasAllStores()) {
            return null;
        }

        return [
            'name' => $storeId === null ? __('Tous les magasins') : (string) Store::query()->whereKey($storeId)->value('name'),
            'sales' => array_map(fn (array $period): Money => SalesReport::forStore($storeId, ...$period), $this->periods()),
        ];
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.sales-stats', [
            'ownSales' => $this->ownSales($user),
            'storeSales' => $this->storeSales($user),
            'stores' => $user->hasAllStores() && $user->can('sales.view_store') ? Store::query()->orderBy('name')->get() : collect(),
        ]);
    }
}
