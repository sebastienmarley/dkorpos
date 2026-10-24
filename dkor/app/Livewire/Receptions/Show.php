<?php

namespace App\Livewire\Receptions;

use App\Models\Product;
use App\Models\Reception;
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

    /** @var array<int, string> Quantité à recevoir par ligne de réception. */
    public array $quantities = [];

    /** @var array<int, string> Partie endommagée de la quantité reçue, par ligne de réception. */
    public array $damaged = [];

    /** @var array<int, string> Quantité annoncée par le fournisseur, mais non reçue, par ligne de réception. */
    public array $short = [];

    public string $overshipSearch = '';

    public string $overshipQuantity = '1';

    public string $overshipOrderId = '';

    public function mount(Reception $reception): void
    {
        $this->reception = $reception;
        $this->fillFromModel();
    }

    public function saveQuantities(): void
    {
        $this->authorize('receptions.create');

        $this->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantities.*' => ['required', 'integer', 'min:0', 'max:99999'],
            'damaged.*' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'short.*' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $this->attempt(function (): void {
            foreach ($this->reception->lines()->with('orderLine.order')->get() as $line) {
                $this->reception->setLineQuantity(
                    $line,
                    (int) ($this->quantities[$line->id] ?? $line->quantity),
                    (int) ($this->damaged[$line->id] ?? $line->quantity_damaged),
                    (int) ($this->short[$line->id] ?? $line->quantity_short),
                );
            }

            $this->reception->updateDetails($this->reference, $this->notes);
        }, __('Réception sauvegardée.'));
    }

    public function removeLine(int $lineId): void
    {
        $this->authorize('receptions.create');

        $line = $this->reception->lines()->findOrFail($lineId);

        $this->attempt(fn () => $this->reception->removeLine($line), __('Ligne retirée.'));
        $this->fillFromModel();
    }

    public function complete(): void
    {
        $this->authorize('receptions.create');

        $this->saveQuantities();

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

        $this->fillFromModel();
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
        $this->fillFromModel();
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
            ->where(fn ($query) => $query->where('model', 'like', '%'.$term.'%')->orWhere('supplier_model', 'like', '%'.$term.'%'))
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

    private function fillFromModel(): void
    {
        $this->reception->refresh()->unsetRelation('lines');
        $this->reference = $this->reception->reference ?? '';
        $this->notes = $this->reception->notes ?? '';
        $lines = $this->reception->lines()->get();
        $this->quantities = $lines->mapWithKeys(fn ($line) => [$line->id => (string) $line->quantity])->all();
        $this->damaged = $lines->mapWithKeys(fn ($line) => [$line->id => (string) $line->quantity_damaged])->all();
        $this->short = $lines->mapWithKeys(fn ($line) => [$line->id => (string) $line->quantity_short])->all();
    }

    public function render(): View
    {
        $this->reception->load(['supplier', 'receiver', 'lines.product', 'lines.orderLine.order', 'lines.orderLine.customerOrderLine.order.customer', 'lines.orderLine.customerOrderLine.product', 'lines.orderLine.customerOrderLine.part']);

        return view('livewire.receptions.show')
            ->layout('layouts.app', ['title' => $this->reception->number]);
    }
}
