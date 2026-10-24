<?php

namespace App\Livewire\Inventory;

use App\Actions\AnnualInventoryFinalUpdate;
use App\Enums\InventoryStatus;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountResult;
use App\Models\Product;
use App\Support\Money;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Une prise d'inventaire (issue #88) : saisie des quantités par type de compte, gel du stock, finalisation, puis
 * rapport en lecture seule et export CSV.
 */
class CountShow extends Component
{
    public InventoryCount $count;

    #[Url(as: 'type')]
    public string $type = 'in_stock';

    /** @var array<int, string> Quantité comptée saisie, par id de ligne. */
    public array $quantities = [];

    /** @var array<int, string> Note de variance, par id de ligne. */
    public array $notes = [];

    public bool $showAdd = false;

    public string $productSearch = '';

    public function mount(InventoryCount $count): void
    {
        $this->authorize('inventory.count');

        $this->count = $count;
        $this->fillInputs();
    }

    public function updatedQuantities(string $value, int|string $lineId): void
    {
        $this->saveLine((int) $lineId);
    }

    public function updatedNotes(string $value, int|string $lineId): void
    {
        $this->saveLine((int) $lineId);
    }

    public function keep(int $lineId): void
    {
        $this->authorize('inventory.count');

        $line = $this->line($lineId);

        $this->apply(fn () => $this->count->recordCount($line, null, ! $line->is_kept, $this->notes[$lineId] ?? null, auth()->user()));
    }

    public function openAdd(): void
    {
        $this->authorize('inventory.count');

        $this->reset('productSearch');
        $this->showAdd = true;
    }

    public function addProduct(int $productId): void
    {
        $this->authorize('inventory.count');

        $this->validate(['type' => ['required', Rule::in(array_keys(InventoryCount::countTypes()))]]);

        $this->apply(fn () => $this->count->addLine(Product::findOrFail($productId), InventoryStatus::from($this->type)));
        $this->showAdd = false;
    }

    public function freezeStock(): void
    {
        $this->authorize('inventory.adjust');

        $this->apply(fn () => $this->count->freezeStock(auth()->user()), __('Stock gelé : comptez-le maintenant.'));
        $this->type = InventoryStatus::InStock->value;
    }

    public function abandon(): void
    {
        $this->authorize('inventory.adjust');

        $this->apply(fn () => $this->count->abandon(), __('Prise d\'inventaire abandonnée.'));
    }

    public function finalize(AnnualInventoryFinalUpdate $finalUpdate): void
    {
        $this->authorize('inventory.adjust');

        $this->apply(fn () => $finalUpdate->handle($this->count, auth()->user()), __('Prise d\'inventaire finalisée : les ajustements sont passés.'));
    }

    public function export(): StreamedResponse
    {
        $this->authorize('inventory.count');

        $lines = $this->linesQuery()->get();
        $types = InventoryCount::countTypes();
        $filename = 'inventaire_'.$this->count->counted_on->toDateString().'.csv';
        $money = fn (?Money $value): string => $value === null ? '' : str_replace('.', ',', $value->toDecimal());
        $zoneDetails = $this->count->results()->with('zone')->whereNotNull('inventory_zone_id')->get()
            ->groupBy(fn (InventoryCountResult $result): string => $result->product_id.'-'.$result->status->value)
            ->map(fn ($results): string => $results->map(fn (InventoryCountResult $result): string => $result->zone?->labelWithStore().' : '.$result->quantity)->implode(' ; '));

        return response()->streamDownload(function () use ($lines, $types, $money, $zoneDetails): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\u{FEFF}");
            fputcsv($output, [
                __('Type de compte'), __('Produit'), __('Attendu'), __('Compté'), __('Écart'), __('Valeur de l\'écart ($)'), __('Note'), __('Détail par zone'),
            ], ';', '"', '');

            foreach ($lines as $line) {
                fputcsv($output, [
                    $types[$line->status->value],
                    $line->product->display_name,
                    $line->expected(),
                    $line->countedLabel(),
                    $line->difference(),
                    $money($line->differenceValue()),
                    $line->note,
                    $zoneDetails->get($line->product_id.'-'.$line->status->value, ''),
                ], ';', '"', '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function saveLine(int $lineId): void
    {
        $this->authorize('inventory.count');

        $this->validate([
            "quantities.$lineId" => ['nullable', 'integer', 'min:0', 'max:99999'],
            "notes.$lineId" => ['nullable', 'string', 'max:200'],
        ]);

        $line = $this->line($lineId);
        $quantity = $this->quantities[$lineId] ?? '';

        $this->apply(fn () => $this->count->recordCount($line, $quantity === '' ? null : (int) $quantity, false, $this->notes[$lineId] ?? null, auth()->user()));
    }

    /**
     * Exécute une opération sur la prise, affiche l'erreur métier ou le message, puis recharge la prise.
     */
    private function apply(callable $operation, ?string $success = null): void
    {
        try {
            $operation();
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
            $this->count->refresh();
            $this->fillInputs();

            return;
        }

        $this->count->refresh();
        $this->fillInputs();

        if ($success !== null) {
            Flux::toast(text: $success, variant: 'success');
        }
    }

    private function line(int $lineId): InventoryCountLine
    {
        return $this->count->lines()->findOrFail($lineId);
    }

    private function fillInputs(): void
    {
        $lines = $this->count->lines()->get();

        $this->quantities = $lines->mapWithKeys(fn (InventoryCountLine $line): array => [$line->id => $line->counted_quantity === null ? '' : (string) $line->counted_quantity])->all();
        $this->notes = $lines->mapWithKeys(fn (InventoryCountLine $line): array => [$line->id => (string) $line->note])->all();
    }

    /** @return Builder<InventoryCountLine> */
    private function linesQuery(): Builder
    {
        return $this->count->lines()->getQuery()
            ->with(['product.inventoryStock', 'counter', 'adjustment.movement'])
            ->join('products', 'products.id', '=', 'inventory_count_lines.product_id')
            ->orderBy('products.model')
            ->select('inventory_count_lines.*');
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
        $lines = $this->linesQuery()->get();
        $byType = $lines->groupBy(fn (InventoryCountLine $line): string => $line->status->value);

        $totals = collect(InventoryCount::countTypes())->map(function (string $label, string $status) use ($byType): array {
            /** @var \Illuminate\Support\Collection<int, InventoryCountLine> $typeLines */
            $typeLines = $byType->get($status, collect());

            return [
                'label' => $label,
                'lines' => $typeLines->count(),
                'uncounted' => $typeLines->reject->isCounted()->count(),
                'value' => Money::sum($typeLines->map(fn (InventoryCountLine $line): Money => $line->differenceValue() ?? Money::zero())),
            ];
        });

        $values = $lines->map(fn (InventoryCountLine $line): Money => $line->differenceValue() ?? Money::zero());

        return view('livewire.inventory.count-show', [
            'types' => InventoryCount::countTypes(),
            'sheetStatuses' => $this->count->sheets()->distinct()->pluck('status')->map(fn (InventoryStatus $status): string => $status->value)->all(),
            'typeLines' => $byType->get($this->type, collect()),
            'totals' => $totals,
            'losses' => Money::sum($values->filter->isNegative())->negate(),
            'gains' => Money::sum($values->filter->isPositive()),
            'productResults' => $this->showAdd ? $this->searchProducts() : collect(),
        ])->layout('layouts.app', ['title' => __('Prise d\'inventaire du :date', ['date' => $this->count->counted_on->toDateString()])]);
    }
}
