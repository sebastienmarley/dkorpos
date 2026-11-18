<?php

namespace App\Livewire\Catalog;

use App\Enums\PromotionType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Supplier;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Promotions temporaires (issue #84). Une promotion n'est jamais supprimée : les commandes qui l'ont reçue la
 * conservent. On la désactive plutôt.
 */
class Promotions extends Component
{
    use WithPagination;

    /** Promotions par page sur un téléphone (xs). Plus large, la liste est complète. */
    public const int MOBILE_PER_PAGE = 5;

    /** Mis par la page selon la largeur de l'écran : 5 en xs, aucune limite (null) au-delà. */
    public ?int $perPage = null;

    public const string STATUS_CURRENT = 'current';

    public const string STATUS_UPCOMING = 'upcoming';

    public const string STATUS_ENDED = 'ended';

    public const string STATUS_ARCHIVED = 'archived';

    /** Sortes de cibles qu'une promotion peut viser. */
    public const array TARGET_TYPES = ['department', 'category', 'supplier', 'product'];

    public string $search = '';

    /** Statut affiché : la page s'ouvre sur les promotions en cours. Les archivées sont réservées à la gestion. */
    public string $statusFilter = self::STATUS_CURRENT;

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
        // « Tout le magasin » revient dans le menu des départements, où il a été choisi.
        $this->targets = $promotion->targets()->with('target')->orderBy('id')->get()
            ->map(fn (PromotionTarget $target): array => $target->target_type === PromotionTarget::ALL
                ? ['type' => 'department', 'value' => PromotionTarget::ALL]
                : [
                    'type' => $target->target_type,
                    'value' => (string) $target->target_id,
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
     * Identifiant de chaque cible. Un produit est saisi par son ID.
     *
     * @return list<array{target_type: string, target_id: int|null}>
     */
    private function resolveTargets(): array
    {
        $errors = [];
        $resolved = [];

        foreach ($this->targets as $index => $target) {
            // « Tous » des menus département et catégorie : tout le magasin, pour un rabais en pourcentage seulement.
            if (in_array($target['type'], ['department', 'category'], true) && $target['value'] === PromotionTarget::ALL) {
                if ($this->type !== PromotionType::Percent->value) {
                    $errors["targets.{$index}.value"] = __('Tout le magasin n\'est offert que pour un rabais en pourcentage.');

                    continue;
                }

                $resolved[PromotionTarget::ALL] = ['target_type' => PromotionTarget::ALL, 'target_id' => null];

                continue;
            }

            $id = match ($target['type']) {
                'department' => Department::query()->whereKey((int) $target['value'])->value('id'),
                'category' => Category::query()->whereKey((int) $target['value'])->value('id'),
                'supplier' => Supplier::query()->whereKey((int) $target['value'])->value('id'),
                'product' => ctype_digit((string) $target['value'])
                    ? (Product::query()->whereKey((int) $target['value'])->value('id') ?? __('Aucun produit avec cet ID.'))
                    : __('Entrez l\'ID du produit.'),
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

    /** @return array<string, Collection<int, Department>|Collection<int, Category>|Collection<int, Supplier>> */
    public function getTargetOptions(): array
    {
        return [
            'department' => Department::query()->orderBy('name')->get(),
            'category' => Category::query()->with('department')->orderBy('name')->get(),
            'supplier' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** Créer ou modifier des promotions donne la recherche et les archivées ; la consultation seule ne les a pas. */
    private function canManagePromotions(): bool
    {
        $user = auth()->user();

        return $user->can('promotions.edit') || $user->can('promotions.create');
    }

    /**
     * Statuts offerts dans le filtre, selon les droits.
     *
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            self::STATUS_CURRENT => __('En cours'),
            self::STATUS_UPCOMING => __('À venir'),
            self::STATUS_ENDED => __('Terminées'),
            ...($this->canManagePromotions() ? [self::STATUS_ARCHIVED => __('Archivées')] : []),
        ];
    }

    public function render(): View
    {
        $canManage = $this->canManagePromotions();
        $statusOptions = $this->statusOptions();
        $status = array_key_exists($this->statusFilter, $statusOptions) ? $this->statusFilter : self::STATUS_CURRENT;
        $term = $canManage ? trim($this->search) : '';
        $today = today();

        $promotions = Promotion::query()
            ->with('targets.target')
            ->when($term !== '', fn ($query) => $query->whereLike('name', '%'.$term.'%'))
            ->tap(fn ($query) => match ($status) {
                self::STATUS_CURRENT => $query->inEffectOn($today)->orderBy('ends_on'),
                self::STATUS_UPCOMING => $query->where('is_active', true)->whereDate('starts_on', '>', $today)->orderBy('starts_on'),
                self::STATUS_ENDED => $query->where('is_active', true)->whereDate('ends_on', '<', $today)->orderByDesc('ends_on'),
                self::STATUS_ARCHIVED => $query->where('is_active', false)->orderByDesc('starts_on'),
            })
            ->orderBy('name');

        // Seule la limite mobile est acceptée : une autre valeur envoyée par la page redonne la liste complète.
        $promotions = $this->perPage === self::MOBILE_PER_PAGE
            ? $promotions->paginate(self::MOBILE_PER_PAGE)
            : $promotions->get();

        return view('livewire.catalog.promotions', [
            'promotions' => $promotions,
            'canManage' => $canManage,
            'statusOptions' => $statusOptions,
            'promotionTypes' => PromotionType::cases(),
        ])->layout('layouts.app', ['title' => __('Promotions')]);
    }
}
