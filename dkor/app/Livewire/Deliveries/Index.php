<?php

namespace App\Livewire\Deliveries;

use App\Enums\DeliveryMethod;
use App\Enums\StoreType;
use App\Models\CustomerOrderDelivery;
use App\Models\Store;
use App\Support\DeliveryBooking;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Grille des livraisons d'un magasin par semaine : chaque demi-journée montre ses places (camions avec un livreur à
 * l'horaire) et les livraisons planifiées.
 */
class Index extends Component
{
    /** Une date de la semaine affichée (la semaine commence le dimanche). */
    #[Url]
    public string $date = '';

    #[Url(as: 'store')]
    public string $storeId = '';

    public function mount(): void
    {
        $this->date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) ? $this->date : Carbon::today()->toDateString();

        if ($this->storeId === '') {
            $this->storeId = (string) (auth()->user()->store_id ?? $this->getStores()->first()->id ?? '');
        }
    }

    public function previousWeek(): void
    {
        $this->date = $this->weekStart()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->date = $this->weekStart()->addWeek()->toDateString();
    }

    public function goToCurrentWeek(): void
    {
        $this->date = Carbon::today()->toDateString();
    }

    public function weekStart(): Carbon
    {
        return Carbon::parse($this->date)->startOfWeek(Carbon::SUNDAY);
    }

    /** @return Collection<int, Store> */
    public function getStores(): Collection
    {
        return Store::query()->where('type', StoreType::Physical)->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Livraisons actives de la semaine.
     *
     * @return Collection<int, CustomerOrderDelivery>
     */
    public function getDeliveries(): Collection
    {
        $start = $this->weekStart();

        return CustomerOrderDelivery::query()
            ->active()
            ->with(['order.customer', 'lines.line.product', 'lines.line.part'])
            ->where('method', DeliveryMethod::Internal)
            ->where('store_id', (int) $this->storeId)
            ->whereBetween('scheduled_date', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()])
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        $hasStore = $this->storeId !== '';

        return view('livewire.deliveries.index', [
            'stores' => $this->getStores(),
            'start' => $this->weekStart(),
            'halfDays' => $hasStore ? DeliveryBooking::slots((int) $this->storeId, $this->weekStart()->toDateString(), 7) : [],
            'deliveries' => $hasStore ? $this->getDeliveries() : new Collection,
        ])->layout('layouts.app', ['title' => __('Livraisons')]);
    }
}
