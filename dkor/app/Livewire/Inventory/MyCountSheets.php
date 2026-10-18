<?php

namespace App\Livewire\Inventory;

use App\Enums\InventoryCountStatus;
use App\Models\InventoryCountScan;
use App\Models\InventoryCountSheet;
use App\Models\Product;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Mes comptes » (ADR 0011) : la page mobile du compteur. Il voit seulement ses feuilles, lit les codes-barres à la
 * douchette (qui tape le code et Entrée, comme un clavier), corrige ou annule une lecture, puis termine sa feuille.
 */
class MyCountSheets extends Component
{
    #[Url(as: 'sheet')]
    public ?int $sheetId = null;

    public string $code = '';

    public string $quantity = '1';

    /** @var array<int, string> Quantité corrigée, par id de produit. */
    public array $quantities = [];

    public ?int $lastScanId = null;

    public string $productSearch = '';

    public function mount(): void
    {
        $this->authorize('inventory.count');

        if ($this->sheetId !== null) {
            $this->fillQuantities();
        }
    }

    public function select(int $sheetId): void
    {
        $this->sheetId = $this->mySheet($sheetId)->id;
        $this->reset('code', 'lastScanId', 'productSearch');
        $this->quantity = '1';
        $this->fillQuantities();
    }

    public function back(): void
    {
        $this->reset('sheetId', 'code', 'lastScanId', 'productSearch');
    }

    public function start(): void
    {
        $this->apply(fn () => $this->currentSheet()->start(auth()->user()));
    }

    public function scan(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $this->apply(function (): void {
            $scan = $this->currentSheet()->scan(auth()->user(), $this->code, (int) $this->quantity);
            $this->lastScanId = $scan->id;
        });

        $this->code = '';
        $this->quantity = '1';
    }

    public function undo(): void
    {
        $this->apply(function (): void {
            $this->currentSheet()->undoLastScan(auth()->user());
            $this->lastScanId = null;
        }, __('Dernière lecture annulée.'));
    }

    public function updatedQuantities(string $value, int|string $productId): void
    {
        if (! preg_match('/^\d+$/', $value)) {
            $this->fillQuantities();

            return;
        }

        $this->apply(fn () => $this->currentSheet()->setQuantity(auth()->user(), Product::findOrFail((int) $productId), (int) $value));
    }

    public function associate(int $productId): void
    {
        $scan = InventoryCountScan::query()->where('inventory_count_sheet_id', $this->sheetId)->findOrFail($this->lastScanId);

        $this->apply(fn () => $this->currentSheet()->associate($scan, Product::findOrFail($productId), auth()->user()), __('Code associé au produit.'));
        $this->reset('productSearch');
    }

    public function finish(): void
    {
        $this->apply(fn () => $this->currentSheet()->finish(auth()->user()), __('Feuille terminée.'));
    }

    /**
     * Exécute une opération sur la feuille et affiche l'erreur métier ou le message.
     */
    private function apply(callable $operation, ?string $success = null): void
    {
        $this->authorize('inventory.count');

        try {
            $operation();
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
            $this->fillQuantities();

            return;
        }

        $this->fillQuantities();

        if ($success !== null) {
            Flux::toast(text: $success, variant: 'success');
        }
    }

    private function mySheet(int $sheetId): InventoryCountSheet
    {
        return InventoryCountSheet::query()->where('assigned_to', auth()->id())->findOrFail($sheetId);
    }

    private function currentSheet(): InventoryCountSheet
    {
        return $this->mySheet((int) $this->sheetId);
    }

    private function fillQuantities(): void
    {
        $this->quantities = $this->sheetId === null
            ? []
            : $this->mySheet($this->sheetId)->lines()->pluck('quantity', 'product_id')->map(fn ($quantity): string => (string) $quantity)->all();
    }

    /** @return Collection<int, Product> */
    private function searchProducts(): Collection
    {
        return Product::query()
            ->where(function ($q) {
                $q->where('model', 'like', '%'.$this->productSearch.'%')
                    ->orWhere('supplier_model', 'like', '%'.$this->productSearch.'%');
            })
            ->orderBy('model')
            ->limit(8)
            ->get();
    }

    public function render(): View
    {
        $sheet = $this->sheetId ? $this->mySheet($this->sheetId)->load(['zone', 'inventoryCount', 'lines.product']) : null;
        $lastScan = $this->lastScanId ? InventoryCountScan::query()->with('product')->where('inventory_count_sheet_id', $this->sheetId)->find($this->lastScanId) : null;

        return view('livewire.inventory.my-count-sheets', [
            'sheets' => InventoryCountSheet::query()
                ->with(['zone', 'inventoryCount'])
                ->where('assigned_to', auth()->id())
                ->whereHas('inventoryCount', fn ($query) => $query->where('status', InventoryCountStatus::InProgress))
                ->orderBy('state')
                ->get(),
            'sheet' => $sheet,
            'lastScan' => $lastScan,
            'canAssociate' => Gate::any(['products.edit', 'inventory.supervise']),
            'productResults' => $lastScan?->isUnknown() && filled($this->productSearch) ? $this->searchProducts() : collect(),
        ])->layout('layouts.app', ['title' => __('Mes comptes')]);
    }
}
