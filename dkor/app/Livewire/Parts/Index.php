<?php

namespace App\Livewire\Parts;

use App\Enums\CustomerOrderLineStatus;
use App\Models\CustomerOrderLine;
use App\Models\Part;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /**
     * Étapes du suivi d'une pièce commandée, d'après le statut de sa ligne de commande client.
     *
     * @var array<string, list<CustomerOrderLineStatus>>
     */
    public const ORDER_STAGES = [
        'ordered' => [CustomerOrderLineStatus::OnOrder, CustomerOrderLineStatus::Ordered, CustomerOrderLineStatus::CancellationRequested],
        'received' => [CustomerOrderLineStatus::Received],
        'handed_over' => [CustomerOrderLineStatus::PickedUp, CustomerOrderLineStatus::Delivered, CustomerOrderLineStatus::Shipped],
    ];

    #[Url(as: 'onglet')]
    public string $tab = 'catalog';

    public string $search = '';

    public string $orderSearch = '';

    public string $orderStage = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOrderSearch(): void
    {
        $this->resetPage('ordersPage');
    }

    public function updatedOrderStage(): void
    {
        $this->resetPage('ordersPage');
    }

    /** @return LengthAwarePaginator<int, Part> */
    public function getParts(): LengthAwarePaginator
    {
        return Part::query()
            ->with(['supplier', 'products'])
            ->when(filled(trim($this->search)), fn ($query) => $query->matching($this->search))
            ->orderBy('model')
            ->paginate(10);
    }

    /**
     * Pièces commandées pour les clients (lignes de commande client), les plus récentes d'abord. La recherche porte
     * sur la pièce (modèle, description), le client, le numéro de la commande client ou de la commande fournisseur.
     *
     * @return LengthAwarePaginator<int, CustomerOrderLine>
     */
    public function getPartOrders(): LengthAwarePaginator
    {
        $term = trim($this->orderSearch);

        return CustomerOrderLine::query()
            ->whereNotNull('part_id')
            ->with(['part.supplier', 'order.customer', 'supplierOrderLine.order'])
            ->when(isset(self::ORDER_STAGES[$this->orderStage]), fn (Builder $query) => $query->whereIn('status', self::ORDER_STAGES[$this->orderStage]))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereHas('part', fn (Builder $part) => $part->matching($term))
                ->orWhereHas('order.customer', fn (Builder $customer) => $customer->matching($term))
                ->orWhereHas('supplierOrderLine.order', fn (Builder $order) => $order->where('number', 'like', '%'.$term.'%'))
                ->when(ctype_digit($term), fn (Builder $query) => $query->orWhere('customer_order_id', (int) $term))))
            ->latest('id')
            ->paginate(10, pageName: 'ordersPage');
    }

    /**
     * Une pièce choisie ou créée dans « Ajouter une pièce » ouvre sa fiche.
     */
    #[On('part-selected')]
    public function openPart(int $id): void
    {
        $this->redirectRoute('parts.show', ['part' => $id], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.parts.index', [
            'parts' => $this->tab === 'catalog' ? $this->getParts() : null,
            'partOrders' => $this->tab === 'orders' ? $this->getPartOrders() : null,
            'orderStages' => [
                'ordered' => __('Commandée'),
                'received' => __('Reçue — à remettre'),
                'handed_over' => __('Remise'),
            ],
        ])->layout('layouts.app', ['title' => __('Pièces')]);
    }
}
