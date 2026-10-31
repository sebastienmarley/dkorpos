<?php

namespace App\Livewire\Products;

use App\Concerns\SavesTabsOnSwitch;
use App\Enums\StoreType;
use App\Enums\SupplierType;
use App\Models\Category;
use App\Models\Color;
use App\Models\Department;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\Store;
use App\Models\Style;
use App\Models\Supplier;
use App\Rules\UniqueCleanProductModel;
use App\Support\Money;
use Closure;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class Show extends Component
{
    use SavesTabsOnSwitch;

    public Product $product;

    public string $activeTab = 'general';

    // Général
    public string $supplierId = '';

    public string $model = '';

    public string $cleanModel = '';

    public string $supplierModel = '';

    public string $departmentId = '';

    public string $categoryId = '';

    public bool $isDiscontinued = false;

    public bool $isNonOrderable = false;

    public bool $isTaxable = true;

    public bool $isCustom = false;

    public string $cost = '';

    // Description
    public string $colorId = '';

    public string $styleId = '';

    public string $imap = '';

    public string $newUpc = '';

    public string $collection = '';

    public string $description = '';

    public string $length = '';

    public string $width = '';

    public string $height = '';

    public string $weight = '';

    /** @var array<int, string> emplacement habituel du produit, par magasin (issue #96) */
    public array $habitualLocations = [];

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->fillFromModel();
        $this->habitualLocations = $product->storeLocations()->pluck('inventory_location_id', 'store_id')->map(fn (int $id): string => (string) $id)->all();
    }

    /** Note l'emplacement habituel du produit dans l'entrepôt d'un magasin (proposé au rangement). */
    public function saveHabitualLocation(int $storeId): void
    {
        $this->authorize('products.edit');

        $locationId = $this->habitualLocations[$storeId] ?? '';

        try {
            $this->product->setHabitualLocation($storeId, filled($locationId) ? (int) $locationId : null);
        } catch (DomainException $exception) {
            $this->addError('habitualLocations.'.$storeId, $exception->getMessage());

            return;
        }

        Flux::toast(text: __('Emplacement habituel enregistré.'), variant: 'success');
    }

    /**
     * Emplacements actifs proposés, par magasin physique qui en a.
     *
     * @return SupportCollection<int, array{store: Store, locations: Collection<int, InventoryLocation>}>
     */
    public function getLocationsByStore(): SupportCollection
    {
        return Store::query()->where('type', StoreType::Physical)->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Store $store): array => [
                'store' => $store,
                'locations' => InventoryLocation::query()->ofStore($store->id)->selectable()->with('zone')->orderBy('code')->get(),
            ])
            ->filter(fn (array $entry): bool => $entry['locations']->isNotEmpty())
            ->values();
    }

    public function updatedModel(string $value): void
    {
        $this->cleanModel = UniqueCleanProductModel::clean($value);
    }

    /**
     * Le département ne sert qu'à filtrer les catégories : on retire une catégorie qui n'y appartient plus.
     */
    public function updatedDepartmentId(): void
    {
        if (filled($this->categoryId) && ! $this->getCategories()->contains('id', (int) $this->categoryId)) {
            $this->categoryId = '';
        }
    }

    /**
     * @return array<string, array{save: string, fields: array<int, string>}>
     */
    protected function tabForms(): array
    {
        return [
            'general' => ['save' => 'saveGeneral', 'fields' => ['supplierId', 'model', 'cleanModel', 'supplierModel', 'departmentId', 'categoryId', 'isDiscontinued', 'isNonOrderable', 'isTaxable', 'isCustom', 'cost']],
            'description' => ['save' => 'saveDescription', 'fields' => ['colorId', 'styleId', 'imap', 'collection', 'description', 'length', 'width', 'height', 'weight']],
        ];
    }

    protected function closeRoute(): string
    {
        return 'products.index';
    }

    public function saveGeneral(): void
    {
        $this->authorize('products.edit');

        $this->cost = $this->normalizeCost($this->cost);
        $this->cleanModel = UniqueCleanProductModel::clean($this->model);

        $this->validate([
            'supplierId' => ['required', 'exists:suppliers,id'],
            'model' => ['required', 'string', 'max:255', new UniqueCleanProductModel($this->supplierId, $this->product->id), new UniqueCleanProductModel($this->supplierId, $this->product->id, 'supplier_clean_model')],
            'supplierModel' => [
                'required', 'string', 'max:255',
                new UniqueCleanProductModel($this->supplierId, $this->product->id, 'supplier_clean_model'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== $this->product->supplier_model && $this->product->isInPriceList()) {
                        $fail(__('Le modèle fournisseur ne peut pas être modifié : le produit est dans une liste de prix.'));
                    }

                    if (filled($value) && UniqueCleanProductModel::clean($value) !== $this->cleanModel) {
                        $fail(__('Le modèle fournisseur doit correspondre au modèle une fois nettoyé.'));
                    }
                },
            ],
            'departmentId' => ['nullable', 'exists:departments,id'],
            'categoryId' => [
                'nullable',
                Rule::exists('categories', 'id')->when(filled($this->departmentId), fn ($rule) => $rule->where('department_id', $this->departmentId)),
            ],
            'isDiscontinued' => ['boolean'],
            'isNonOrderable' => ['boolean'],
            'isTaxable' => ['boolean'],
            'isCustom' => ['boolean'],
            'cost' => ['required', 'numeric', 'min:0.01'],
        ]);

        $this->product->fill([
            'supplier_id' => $this->supplierId,
            'model' => $this->model,
            'clean_model' => $this->cleanModel,
            'supplier_model' => $this->supplierModel,
            'category_id' => filled($this->categoryId) ? $this->categoryId : null,
            'is_discontinued' => $this->isDiscontinued,
            'is_non_orderable' => $this->isNonOrderable,
            'is_taxable' => $this->isTaxable,
            'is_custom' => $this->isCustom,
            'cost' => Money::parse($this->cost),
        ])->save();

        $this->product->refresh();
        Flux::toast(text: __('Général sauvegardé.'), variant: 'success');
    }

    public function saveDescription(): void
    {
        $this->authorize('products.edit');

        $this->validate([
            'colorId' => ['nullable', 'exists:colors,id'],
            'styleId' => ['nullable', 'exists:styles,id'],
            'imap' => ['nullable', 'numeric', 'min:0'],
            'collection' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->product->fill([
            'color_id' => filled($this->colorId) ? $this->colorId : null,
            'style_id' => filled($this->styleId) ? $this->styleId : null,
            'imap' => filled($this->imap) ? Money::parse($this->imap) : null,
            'collection' => filled($this->collection) ? $this->collection : null,
            'description' => filled($this->description) ? $this->description : null,
            'length' => filled($this->length) ? $this->length : null,
            'width' => filled($this->width) ? $this->width : null,
            'height' => filled($this->height) ? $this->height : null,
            'weight' => filled($this->weight) ? $this->weight : null,
        ])->save();

        Flux::toast(text: __('Description sauvegardée.'), variant: 'success');
    }

    public function addUpc(): void
    {
        $this->authorize('products.edit');

        $this->newUpc = trim($this->newUpc);

        $this->validate(['newUpc' => ['required', 'regex:/^\d+$/', 'max:255']]);

        $this->product->upcs()->firstOrCreate(['upc' => $this->newUpc]);
        $this->reset('newUpc');
    }

    public function removeUpc(int $upcId): void
    {
        $this->authorize('products.edit');

        $this->product->upcs()->whereKey($upcId)->delete();
    }

    /**
     * Prix de vente du coût en cours de saisie (0 $ tant que le coût n'est pas un montant valide).
     */
    public function getSellingPrice(): Money
    {
        try {
            $cost = Money::parse($this->cost);
        } catch (InvalidArgumentException) {
            $cost = Money::zero();
        }

        return Product::sellingPriceFor($cost, Supplier::find($this->supplierId)->price_multiplier ?? 1.0);
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::where('type', SupplierType::Product)->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Department> */
    public function getDepartments(): Collection
    {
        return Department::orderBy('name')->get();
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return Category::when(filled($this->departmentId), fn ($q) => $q->where('department_id', $this->departmentId))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Color> */
    public function getColors(): Collection
    {
        return Color::orderBy('name')->get();
    }

    /** @return Collection<int, Style> */
    public function getStyles(): Collection
    {
        return Style::orderBy('name')->get();
    }

    private function normalizeCost(string $value): string
    {
        return Money::tryParse($value)?->toDecimal() ?? $value;
    }

    private function fillFromModel(): void
    {
        $this->supplierId = (string) $this->product->supplier_id;
        $this->model = $this->product->model;
        $this->cleanModel = $this->product->clean_model;
        $this->supplierModel = $this->product->supplier_model ?? '';
        $this->departmentId = (string) ($this->product->category->department_id ?? '');
        $this->categoryId = (string) ($this->product->category_id ?? '');
        $this->isDiscontinued = $this->product->is_discontinued;
        $this->isNonOrderable = $this->product->is_non_orderable;
        $this->isTaxable = $this->product->is_taxable;
        $this->isCustom = $this->product->is_custom;
        $this->cost = $this->product->cost->toDecimal();
        $this->colorId = (string) ($this->product->color_id ?? '');
        $this->styleId = (string) ($this->product->style_id ?? '');
        $this->imap = $this->product->imap?->toDecimal() ?? '';
        $this->collection = $this->product->collection ?? '';
        $this->description = $this->product->description ?? '';
        $this->length = $this->product->length !== null ? (string) $this->product->length : '';
        $this->width = $this->product->width !== null ? (string) $this->product->width : '';
        $this->height = $this->product->height !== null ? (string) $this->product->height : '';
        $this->weight = $this->product->weight !== null ? (string) $this->product->weight : '';
    }

    public function render(): View
    {
        return view('livewire.products.show', [
            'supplierModelLocked' => $this->product->isInPriceList(),
            'upcs' => $this->product->upcs()->orderBy('id')->get(),
            'parts' => $this->product->parts()->with('supplier')->orderBy('model')->get(),
        ])
            ->layout('layouts.app', ['title' => $this->product->model]);
    }
}
