<?php

namespace App\Livewire\Receptions;

use App\Models\Product;
use App\Models\Reception;
use App\Models\ReceptionLine;
use App\Models\SupplierOrder;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Component;

class Show extends Component
{
    public Reception $reception;

    public string $reference = '';

    public string $notes = '';

    /** Code UPC balayé : une unité du produit correspondant est comptée. */
    public string $upcScan = '';

    /** Produit (ReceptionLine::groupKey()) ouvert dans le modal de réception. */
    public ?string $groupKey = null;

    public bool $showGroupModal = false;

    public string $received = '0';

    public string $damaged = '0';

    public string $short = '0';

    public string $overshipSearch = '';

    public string $overshipQuantity = '1';

    public string $overshipOrderId = '';

    public function mount(Reception $reception): void
    {
        abort_unless($reception->isAccessibleTo(auth()->user()), 403);

        $this->reception = $reception;
        $this->reference = $reception->reference ?? '';
        $this->notes = $reception->notes ?? '';
    }

    /** La douchette tape le code UPC et Entrée : une unité est comptée dans le produit attendu. */
    public function scanUpc(): void
    {
        $this->authorize('receptions.create');

        $upc = trim($this->upcScan);
        $this->upcScan = '';

        if ($upc === '') {
            return;
        }

        try {
            $lines = $this->reception->receiveScannedUpc($upc);
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(text: __(':label : :received / :expected', [
            'label' => $lines->first()->label,
            'received' => $lines->sum('quantity'),
            'expected' => $lines->sum('quantity_expected'),
        ]), variant: 'success');
    }

    public function openGroup(string $groupKey): void
    {
        $lines = $this->group($groupKey);

        $this->resetValidation();
        $this->groupKey = $groupKey;
        $this->received = (string) $lines->sum('quantity');
        $this->damaged = (string) $lines->sum('quantity_damaged');
        $this->short = (string) $lines->sum('quantity_short');
        $this->showGroupModal = true;
    }

    /** Confirme ce qui est réellement reçu du produit ouvert : il devient vérifié. */
    public function saveGroup(): void
    {
        $this->authorize('receptions.create');

        $this->validate([
            'received' => ['required', 'integer', 'min:0', 'max:99999'],
            'damaged' => ['required', 'integer', 'min:0', 'max:99999'],
            'short' => ['required', 'integer', 'min:0', 'max:99999'],
        ]);

        try {
            $this->reception->receiveGroup((string) $this->groupKey, (int) $this->received, (int) $this->damaged, (int) $this->short);
        } catch (DomainException $e) {
            $this->addError('received', $e->getMessage());

            return;
        }

        $this->closeGroup();
        Flux::toast(text: __('Produit vérifié.'), variant: 'success');
    }

    /** Retire de la réception toutes les lignes du produit ouvert. */
    public function removeGroup(): void
    {
        $this->authorize('receptions.create');

        $lines = $this->group((string) $this->groupKey);

        $this->attempt(function () use ($lines): void {
            foreach ($lines as $line) {
                $this->reception->removeLine($line);
            }
        }, __('Produit retiré de la réception.'));

        $this->closeGroup();
    }

    public function closeGroup(): void
    {
        $this->showGroupModal = false;
        $this->groupKey = null;
    }

    public function saveDetails(): void
    {
        $this->authorize('receptions.create');

        $this->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->attempt(fn () => $this->reception->updateDetails($this->reference, $this->notes), __('Réception sauvegardée.'));
    }

    public function complete(): void
    {
        $this->authorize('receptions.create');

        $this->saveDetails();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $emailed = true;
        $this->attempt(function () use (&$emailed): void {
            $emailed = $this->reception->complete();
        }, __('Réception terminée : l\'inventaire est à jour.'));

        if (! $emailed) {
            Flux::toast(text: __('Le courriel d\'erreur d\'expédition n\'a pas pu être envoyé au fournisseur : avisez-le vous-même.'), variant: 'warning');
        }
    }

    /**
     * Lignes d'un produit de la réception, clients d'abord.
     *
     * @return EloquentCollection<int, ReceptionLine>
     */
    private function group(string $groupKey): EloquentCollection
    {
        $this->reception->unsetRelation('lines');

        return $this->reception->lineGroups()->get($groupKey) ?? abort(404);
    }

    /**
     * Ajoute un article reçu en trop (produit du fournisseur) à la réception, sur la commande choisie.
     */
    public function addOvership(int $productId): void
    {
        $this->authorize('receptions.create');

        $this->validate([
            'overshipQuantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'overshipOrderId' => ['nullable', 'integer'],
        ]);

        $order = filled($this->overshipOrderId)
            ? $this->receptionOrders()->firstWhere('id', (int) $this->overshipOrderId)
            : null;

        $this->attempt(
            fn () => $this->reception->addOvership(Product::findOrFail($productId), (int) $this->overshipQuantity, $order),
            __('Article reçu en trop ajouté.'),
        );

        $this->reset(['overshipSearch', 'overshipOrderId']);
        $this->overshipQuantity = '1';
    }

    /**
     * Produits du fournisseur dont le modèle ou le modèle fournisseur contient le terme (2 caractères minimum).
     *
     * @return EloquentCollection<int, Product>
     */
    public function getOvershipResults(): EloquentCollection
    {
        $term = trim($this->overshipSearch);

        if (mb_strlen($term) < 2) {
            return new EloquentCollection;
        }

        return Product::query()
            ->where('supplier_id', $this->reception->supplier_id)
            ->where('is_custom', false)
            ->where(fn ($query) => $query->whereLike('model', '%'.$term.'%')->orWhereLike('supplier_model', '%'.$term.'%'))
            ->orderBy('model')
            ->limit(8)
            ->get();
    }

    /**
     * Commandes ouvertes présentes dans la réception (celles qui peuvent recevoir un article en trop).
     *
     * @return Collection<int, SupplierOrder>
     */
    public function receptionOrders(): Collection
    {
        return $this->reception->lines()->with('orderLine.order')->get()
            ->map(fn ($line) => $line->orderLine->order)
            ->filter(fn (SupplierOrder $order): bool => $order->status->isOpen())
            ->unique('id')
            ->values();
    }

    public function discard(): void
    {
        $this->authorize('receptions.create');

        try {
            $this->reception->discard();
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->redirectRoute('receptions.index', navigate: true);
    }

    private function attempt(callable $action, string $successMessage): void
    {
        try {
            $action();
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->reception->refresh();
        Flux::toast(text: $successMessage, variant: 'success');
    }

    public function render(): View
    {
        $this->reception->load(['supplier', 'receiver', 'lines.product', 'lines.orderLine.order', 'lines.orderLine.customerOrderLine.order.customer', 'lines.orderLine.customerOrderLine.product', 'lines.orderLine.customerOrderLine.part']);

        $groups = $this->reception->lineGroups();

        return view('livewire.receptions.show', [
            'groups' => $groups,
            'openLines' => $this->showGroupModal && $this->groupKey !== null ? $groups->get($this->groupKey) : null,
        ])
            ->layout('layouts.app', ['title' => $this->reception->number]);
    }
}
