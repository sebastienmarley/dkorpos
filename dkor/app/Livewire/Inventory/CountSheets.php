<?php

namespace App\Livewire\Inventory;

use App\Enums\InventoryCountPass;
use App\Enums\InventoryStatus;
use App\Models\InventoryCount;
use App\Models\InventoryCountScan;
use App\Models\InventoryCountSheet;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * Supervision des comptes doubles d'une prise d'inventaire (ADR 0011) : préparation et attribution des feuilles,
 * comparaison des comptes A et B, recompte des désaccords, réconciliation et codes inconnus.
 */
class CountSheets extends Component
{
    public InventoryCount $count;

    /** @var array<int, string> Compteur attribué, par id de feuille. */
    public array $assignments = [];

    /** @var array<string, string> Compteur du recompte, par portée (« état-zone »). */
    public array $recountCounters = [];

    /** @var array<string, bool> Retenir le recompte qui ne concorde ni avec A ni avec B, par portée. */
    public array $acceptRecount = [];

    public bool $showAssociate = false;

    public ?int $associatingScanId = null;

    public string $productSearch = '';

    public function mount(InventoryCount $count): void
    {
        $this->authorize('inventory.supervise');

        abort_unless($count->isAccessibleTo(auth()->user()), 403);

        $this->count = $count;
        $this->fillAssignments();
    }

    public function prepare(): void
    {
        $this->authorize('inventory.supervise');

        $this->apply(fn () => $this->count->prepareSheets(), __('Feuilles de compte préparées.'));
    }

    public function updatedAssignments(string $userId, int|string $sheetId): void
    {
        $this->authorize('inventory.supervise');

        if ($userId === '') {
            return;
        }

        $sheet = $this->count->sheets()->findOrFail((int) $sheetId);

        $this->apply(fn () => $this->count->assignSheet($sheet, User::findOrFail((int) $userId)));
    }

    public function createRecount(string $scope): void
    {
        $this->authorize('inventory.supervise');

        [$status, $zoneId] = $this->parseScope($scope);
        $counterId = $this->recountCounters[$scope] ?? '';

        if ($counterId === '') {
            Flux::toast(text: __('Choisissez le compteur du recompte.'), variant: 'danger');

            return;
        }

        $this->apply(fn () => $this->count->createRecount($status, $zoneId, User::findOrFail((int) $counterId)), __('Recompte créé.'));
    }

    public function reconcile(string $scope): void
    {
        $this->authorize('inventory.supervise');

        [$status, $zoneId] = $this->parseScope($scope);

        $this->apply(fn () => $this->count->reconcileScope($status, $zoneId, auth()->user(), $this->acceptRecount[$scope] ?? false), __('Portée réconciliée.'));
    }

    public function openAssociate(int $scanId): void
    {
        $this->authorize('inventory.supervise');

        $this->associatingScanId = $scanId;
        $this->reset('productSearch');
        $this->showAssociate = true;
    }

    public function associate(int $productId): void
    {
        $this->authorize('inventory.supervise');

        $scan = InventoryCountScan::query()->whereIn('inventory_count_sheet_id', $this->count->sheets()->select('id'))->findOrFail($this->associatingScanId);

        $this->apply(fn () => $scan->sheet->associate($scan, Product::findOrFail($productId), auth()->user()), __('Code associé au produit.'));
        $this->associatingScanId = null;
        $this->showAssociate = false;
    }

    /**
     * Exécute une opération sur la prise et affiche l'erreur métier ou le message.
     */
    private function apply(callable $operation, ?string $success = null): void
    {
        try {
            $operation();
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
            $this->fillAssignments();

            return;
        }

        $this->count->refresh();
        $this->fillAssignments();

        if ($success !== null) {
            Flux::toast(text: $success, variant: 'success');
        }
    }

    /** @return array{0: InventoryStatus, 1: int|null} */
    private function parseScope(string $scope): array
    {
        [$status, $zoneId] = explode('-', $scope) + [1 => '0'];

        return [InventoryStatus::from($status), $zoneId === '0' ? null : (int) $zoneId];
    }

    private function fillAssignments(): void
    {
        $this->assignments = $this->count->sheets()->pluck('assigned_to', 'id')->map(fn ($userId): string => (string) $userId)->all();
    }

    /** @return Collection<int, Product> */
    private function searchProducts(): Collection
    {
        return Product::query()
            ->when(filled($this->productSearch), fn ($query) => $query->where(function ($q) {
                $q->where('model', 'like', '%'.$this->productSearch.'%')
                    ->orWhere('supplier_model', 'like', '%'.$this->productSearch.'%');
            }))
            ->orderBy('model')
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        $sheets = $this->count->sheets()->with(['zone', 'assignee'])->get();
        $products = Product::query()->whereIn('id', $this->count->sheets()->join('inventory_count_sheet_lines', 'inventory_count_sheet_lines.inventory_count_sheet_id', '=', 'inventory_count_sheets.id')->select('inventory_count_sheet_lines.product_id'))->get()->keyBy('id');

        $scopes = $sheets
            ->groupBy(fn (InventoryCountSheet $sheet): string => $sheet->status->value.'-'.($sheet->inventory_zone_id ?? 0))
            ->map(function ($scopeSheets) {
                /** @var InventoryCountSheet $first */
                $first = $scopeSheets->first();

                return [
                    'label' => $first->areaLabel(),
                    'sheets' => $scopeSheets->sortBy(fn (InventoryCountSheet $sheet): int => match ($sheet->pass) {
                        InventoryCountPass::A => 0,
                        InventoryCountPass::B => 1,
                        InventoryCountPass::Recount => 2,
                    }),
                    'comparison' => $this->count->compareScope($first->status, $first->inventory_zone_id),
                    'hasRecount' => $scopeSheets->contains(fn (InventoryCountSheet $sheet): bool => $sheet->pass === InventoryCountPass::Recount),
                    'reconciled' => $first->reconciled_at !== null,
                ];
            })
            ->sortBy(fn (array $scope): string => $scope['label']);

        return view('livewire.inventory.count-sheets', [
            'scopes' => $scopes,
            'products' => $products,
            'counters' => User::permission('inventory.count')->where('is_active', true)->orderBy('lastname')->orderBy('firstname')->get(),
            'unknownScans' => InventoryCountScan::query()
                ->with('sheet.zone')
                ->whereIn('inventory_count_sheet_id', $sheets->modelKeys())
                ->whereNull('product_id')
                ->whereNull('undone_at')
                ->latest('id')
                ->get(),
            'productResults' => $this->showAssociate ? $this->searchProducts() : collect(),
        ])->layout('layouts.app', ['title' => __('Feuilles de compte')]);
    }
}
