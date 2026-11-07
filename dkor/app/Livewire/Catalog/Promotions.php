<?php

namespace App\Livewire\Catalog;

use App\Enums\PromotionType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Supplier;
use App\Rules\UniqueCleanProductModel;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Promotions temporaires (issue #84). Une promotion n'est jamais supprimée : les commandes qui l'ont reçue la
 * conservent. On la désactive plutôt.
 */
class Promotions extends Component
{
    /** Sortes de cibles qu'une promotion peut viser. */
    public const array TARGET_TYPES = ['department', 'category', 'supplier', 'product'];

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'percent';

    public string $percent = '';

    public string $amount = '';

    public string $minimumQuantity = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public bool $isActive = true;

    /** @var array<int, array{type: string, value: int|string}> */
    public array $targets = [];

    public function openCreate(): void
    {
        $this->authorize('promotions.create');

        $this->reset(['editingId', 'name', 'type', 'percent', 'amount', 'minimumQuantity', 'isActive']);
        $this->startsOn = today()->toDateString();
        $this->endsOn = today()->addWeek()->toDateString();
        $this->targets = [['type' => 'department', 'value' => '']];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(Promotion $promotion): void
    {
        $this->authorize('promotions.edit');

        $this->editingId = $promotion->id;
        $this->name = $promotion->name;
        $this->type = $promotion->type->value;
        $this->percent = $promotion->percent !== null ? (string) $promotion->percent : '';
        $this->amount = $promotion->amount?->toDecimal() ?? '';
        $this->minimumQuantity = (string) ($promotion->minimum_quantity ?? '');
        $this->startsOn = $promotion->starts_on->toDateString();
        $this->endsOn = $promotion->ends_on->toDateString();
        $this->isActive = $promotion->is_active;
        $this->targets = $promotion->targets()->with('target')->orderBy('id')->get()
            ->map(fn (PromotionTarget $target): array => [
                'type' => $target->target_type,
                'value' => $target->target instanceof Product ? $target->target->model : $target->target_id,
            ])
            ->all();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function addTarget(): void
    {
        $this->targets[] = ['type' => 'department', 'value' => ''];
    }

    public function removeTarget(int $index): void
    {
        unset($this->targets[$index]);
        $this->targets = array_values($this->targets);
    }

    /** Changer la sorte d'une cible vide sa valeur : un identifiant de département ne vaut rien comme fournisseur. */
    public function updatedTargets(mixed $value, ?string $key = null): void
    {
        [$index, $field] = array_pad(explode('.', $key), 2, null);

        if ($field === 'type' && isset($this->targets[(int) $index])) {
            $this->targets[(int) $index]['value'] = '';
        }
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'promotions.edit' : 'promotions.create');

        $this->name = trim($this->name);
        $this->percent = str_replace(',', '.', trim($this->percent));
        $this->amount = str_replace(',', '.', trim($this->amount));
        $this->targets = array_values(array_filter(
            array_map(fn (array $target): array => ['type' => $target['type'], 'value' => is_string($target['value']) ? trim($target['value']) : $target['value']], $this->targets),
            fn (array $target): bool => filled($target['value']),
        ));

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'percent' => [Rule::requiredIf($this->type === PromotionType::Percent->value), 'nullable', 'numeric', 'gt:0', 'max:100'],
            'amount' => [Rule::requiredIf($this->type === PromotionType::FixedAmount->value), 'nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'minimumQuantity' => [Rule::requiredIf($this->type === PromotionType::FixedAmount->value), 'nullable', 'integer', 'min:1', 'max:999'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
            'isActive' => ['boolean'],
            'targets' => ['required', 'array'],
            'targets.*.type' => ['required', Rule::in(self::TARGET_TYPES)],
            'targets.*.value' => ['required'],
        ], ['targets.required' => __('Choisissez au moins un département, une catégorie, un fournisseur ou un produit.')]);

        $resolved = $this->resolveTargets();
        $type = PromotionType::from($this->type);

        DB::transaction(function () use ($resolved, $type): void {
            $promotion = Promotion::query()->updateOrCreate(['id' => $this->editingId], [
                'name' => $this->name,
                'type' => $type,
                'percent' => $type === PromotionType::Percent ? (float) $this->percent : null,
                'amount' => $type === PromotionType::FixedAmount ? Money::parse($this->amount) : null,
                'minimum_quantity' => $type === PromotionType::FixedAmount ? (int) $this->minimumQuantity : null,
                'starts_on' => $this->startsOn,
                'ends_on' => $this->endsOn,
                'is_active' => $this->isActive,
            ]);

            $promotion->targets()->delete();
            $promotion->targets()->createMany($resolved);
        });

        Flux::toast(text: $this->editingId ? __('Promotion mise à jour.') : __('Promotion créée.'), variant: 'success');

        $this->showModal = false;
        $this->reset(['editingId']);
    }

    /**
     * Identifiant de chaque cible. Un produit est saisi par son modèle, comparé sans espaces ni ponctuation.
     *
     * @return list<array{target_type: string, target_id: int}>
     */
    private function resolveTargets(): array
    {
        $errors = [];
        $resolved = [];

        foreach ($this->targets as $index => $target) {
            $id = match ($target['type']) {
                'department' => Department::query()->whereKey((int) $target['value'])->value('id'),
                'category' => Category::query()->whereKey((int) $target['value'])->value('id'),
                'supplier' => Supplier::query()->whereKey((int) $target['value'])->value('id'),
                'product' => $this->productIdForModel((string) $target['value']) ?? __('Aucun produit avec ce modèle ou plusieurs fournisseurs l\'ont : ciblez plutôt le fournisseur.'),
                default => null,
            };

            if (! is_int($id)) {
                $errors["targets.{$index}.value"] = $id ?? __('Cible introuvable.');

                continue;
            }

            $resolved["{$target['type']}:{$id}"] = ['target_type' => $target['type'], 'target_id' => $id];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($resolved);
    }

    /** Identifiant du seul produit qui a ce modèle (null si aucun ou si plusieurs fournisseurs l'ont). */
    private function productIdForModel(string $model): ?int
    {
        $ids = Product::query()->where('clean_model', UniqueCleanProductModel::clean($model))->orderBy('id')->limit(2)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /** @return array<string, Collection<int, Department>|Collection<int, Category>|Collection<int, Supplier>> */
    public function getTargetOptions(): array
    {
        return [
            'department' => Department::query()->orderBy('name')->get(),
            'category' => Category::query()->with('department')->orderBy('name')->get(),
            'supplier' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    public function render(): View
    {
        return view('livewire.catalog.promotions', [
            'promotions' => Promotion::query()
                ->with('targets.target')
                ->when(filled($this->search), fn ($query) => $query->whereLike('name', '%'.trim($this->search).'%'))
                ->orderByDesc('is_active')
                ->orderByDesc('starts_on')
                ->orderBy('name')
                ->get(),
            'promotionTypes' => PromotionType::cases(),
        ])->layout('layouts.app', ['title' => __('Promotions')]);
    }
}
