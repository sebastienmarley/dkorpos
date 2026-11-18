{{-- En xs, 5 promotions par page : la page le dit au composant au chargement et quand la largeur change. --}}
<div
    class="sm:p-6"
    x-data="{
        init() {
            const mobile = window.matchMedia('(max-width: 639px)');
            const sync = () => {
                const perPage = mobile.matches ? {{ \App\Livewire\Catalog\Promotions::MOBILE_PER_PAGE }} : null;
                if ($wire.perPage !== perPage) $wire.$set('perPage', perPage);
            };
            sync();
            mobile.addEventListener('change', sync);
        },
    }"
>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <flux:heading level="1" size="xl">{{ __('Promotions') }}</flux:heading>
            <flux:tooltip :content="__('Rabais temporaires appliqués à l\'étape du paiement, une seule promotion par commande')">
                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
            </flux:tooltip>
        </div>
        @can('promotions.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('Ajouter') }}</flux:button>
        @endcan
    </div>

    {{-- Filtres : la recherche est réservée à ceux qui créent ou modifient des promotions. --}}
    <div class="mb-4 flex flex-wrap gap-3">
        @if ($canManage)
            <div class="w-full sm:w-auto sm:flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Rechercher une promotion…')" />
            </div>
        @endif

        <div class="w-full sm:w-52">
            <flux:select wire:model.live="statusFilter">
                @foreach ($statusOptions as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Résultats du statut choisi (en cours à l'ouverture) --}}
    @if ($promotions->isNotEmpty())
        @php($canEditPromotions = auth()->user()->can('promotions.edit'))
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table :paginate="$promotions instanceof \Illuminate\Pagination\LengthAwarePaginator ? $promotions : null">
                <flux:table.columns>
                    <flux:table.column>{{ __('Statut') }}</flux:table.column>
                    <flux:table.column>{{ __('Promotion') }}</flux:table.column>
                    <flux:table.column class="max-sm:hidden">{{ __('Rabais') }}</flux:table.column>
                    <flux:table.column>{{ __('Période') }}</flux:table.column>
                    <flux:table.column class="max-sm:hidden">{{ __('Produits visés') }}</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($promotions as $promotion)
                        <x-table.row-link :key="$promotion->id" :action="$canEditPromotions ? '$wire.openEdit('.$promotion->id.')' : null" @class(['opacity-50' => ! $promotion->is_active])>
                            <flux:table.cell>
                                @if (! $promotion->is_active)
                                    <flux:badge size="sm">{{ __('Inactive') }}</flux:badge>
                                @elseif ($promotion->isInEffectOn(today()))
                                    <flux:badge size="sm" color="green">{{ __('En cours') }}</flux:badge>
                                @elseif (today()->isBefore($promotion->starts_on))
                                    <flux:badge size="sm" color="blue">{{ __('À venir') }}</flux:badge>
                                @else
                                    <flux:badge size="sm">{{ __('Terminée') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            {{-- Nom long coupé : il est complet au survol et dans l'édition. --}}
                            <flux:table.cell variant="strong">
                                <div class="max-w-32 truncate sm:max-w-40 lg:max-w-56" title="{{ $promotion->name }}">{{ $promotion->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="max-sm:hidden">
                                @if ($promotion->type === \App\Enums\PromotionType::FixedAmount)
                                    {{-- Montant fixe sur deux lignes : le montant, puis le nombre d'articles requis. --}}
                                    <div class="whitespace-nowrap">{{ ($promotion->amount ?? \App\Support\Money::zero())->format() }}</div>
                                    <div class="whitespace-nowrap text-xs text-zinc-400">{{ trans_choice('dès :count article|dès :count articles', (int) $promotion->minimum_quantity) }}</div>
                                @else
                                    <span class="whitespace-nowrap">{{ $promotion->discountLabel() }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $promotion->starts_on->format('Y-m-d') }} → {{ $promotion->ends_on->format('Y-m-d') }}</flux:table.cell>
                            <flux:table.cell class="max-sm:hidden">
                                @php($targetsLabel = $promotion->targets->map->label()->join(', '))
                                <div class="max-w-40 truncate lg:max-w-56" title="{{ $targetsLabel }}">{{ $targetsLabel ?: '—' }}</div>
                            </flux:table.cell>
                        </x-table.row-link>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <flux:text class="mt-3 text-sm text-zinc-400">
            {{ trans_choice(':count promotion|:count promotions', $promotions instanceof \Illuminate\Pagination\LengthAwarePaginator ? $promotions->total() : $promotions->count()) }}
        </flux:text>
    @else
        <div class="flex flex-col items-center gap-2 py-12 text-center">
            <flux:icon name="receipt-percent" class="h-8 w-8 text-zinc-300" />
            <flux:text class="text-zinc-400">{{ __('Aucune promotion pour ces critères.') }}</flux:text>
        </div>
    @endif

    <flux:modal wire:model="showModal" class="w-full max-w-2xl">
        <flux:heading class="mb-1">{{ $editingId ? __('Modifier la promotion') : __('Nouvelle promotion') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="name" :placeholder="__('Nous payons les taxes, 10 % sur l\'éclairage…')" required autofocus />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <div class="flex items-center gap-1.5">
                    <flux:label>{{ __('Sorte de rabais') }}</flux:label>
                    @if ($type === 'fixed_amount')
                        <flux:tooltip :content="__('Le rabais est réparti sur les articles visés au prorata de leur prix.')">
                            <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                        </flux:tooltip>
                    @elseif ($type !== 'percent')
                        <flux:tooltip :content="__('Le prix des articles taxables visés est baissé pour que leur total, taxes incluses, égale le prix avant taxes.')">
                            <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                        </flux:tooltip>
                    @endif
                </div>
                <flux:select wire:model.live="type">
                    @foreach ($promotionTypes as $promotionType)
                        <flux:select.option :value="$promotionType->value">{{ $promotionType->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="type" />
            </flux:field>

            {{-- Dès sm, le rabais et les dates sont sur une même ligne, en champs de même largeur. En xs, Du et Au sont côte à côte. --}}
            <div class="space-y-4 sm:flex sm:items-start sm:gap-4 sm:space-y-0">
                {{-- Clés par type : sans elles, Livewire réutiliserait le champ d'un type pour celui d'un autre. --}}
                @if ($type === 'percent')
                    <flux:field class="sm:min-w-0 sm:flex-1" wire:key="discount-percent">
                        <flux:label>{{ __('Rabais (%)') }}</flux:label>
                        <flux:input wire:model="percent" inputmode="decimal" />
                        <flux:error name="percent" />
                    </flux:field>
                @elseif ($type === 'fixed_amount')
                    <div class="flex flex-wrap gap-4 sm:min-w-0 sm:flex-[2] sm:flex-nowrap" wire:key="discount-fixed-amount">
                        <flux:field class="w-full sm:w-auto sm:min-w-0 sm:flex-1">
                            <flux:label>{{ __('Rabais total') }}</flux:label>
                            <flux:input wire:model="amount" inputmode="decimal" />
                            <flux:error name="amount" />
                        </flux:field>
                        <flux:field class="w-full sm:w-auto sm:min-w-0 sm:flex-1">
                            <flux:label>{{ __('À l\'achat de') }}</flux:label>
                            <flux:input wire:model="minimumQuantity" type="number" min="1" />
                            <flux:error name="minimumQuantity" />
                        </flux:field>
                    </div>
                @endif

                <div class="flex gap-4 sm:min-w-0 sm:flex-[2]">
                    <flux:field class="min-w-0 flex-1">
                        <flux:label>{{ __('Du') }}</flux:label>
                        <flux:input wire:model="startsOn" type="date" required />
                        <flux:error name="startsOn" />
                    </flux:field>
                    <flux:field class="min-w-0 flex-1">
                        <flux:label>{{ __('Au') }}</flux:label>
                        <flux:input wire:model="endsOn" type="date" required />
                        <flux:error name="endsOn" />
                    </flux:field>
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center gap-1.5">
                    <flux:heading size="sm">{{ __('Produits visés') }}</flux:heading>
                    <flux:tooltip :content="__('Un article est visé si son département, sa catégorie, son fournisseur ou son ID de produit figure ici.')">
                        <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                    </flux:tooltip>
                </div>

                @php($targetOptions = $this->getTargetOptions())
                <div class="space-y-2">
                    @foreach ($targets as $index => $target)
                        {{-- En xs : la sorte sur sa ligne, la valeur et Retirer en dessous, en pleine largeur. --}}
                        <div wire:key="target-{{ $index }}" class="flex items-start gap-2 max-sm:flex-wrap max-sm:border-b max-sm:border-zinc-200 max-sm:pb-2 dark:max-sm:border-zinc-700">
                            <div class="w-40 max-sm:w-full max-sm:basis-full">
                                <flux:select wire:model.live="targets.{{ $index }}.type">
                                    <flux:select.option value="department">{{ __('Département') }}</flux:select.option>
                                    <flux:select.option value="category">{{ __('Catégorie') }}</flux:select.option>
                                    <flux:select.option value="supplier">{{ __('Fournisseur') }}</flux:select.option>
                                    <flux:select.option value="product">{{ __('Produit') }}</flux:select.option>
                                </flux:select>
                            </div>
                            <div class="min-w-0 flex-1">
                                @if ($target['type'] === 'product')
                                    <flux:input wire:key="target-{{ $index }}-product" wire:model="targets.{{ $index }}.value" inputmode="numeric" :placeholder="__('ID du produit')" />
                                @else
                                    <flux:select wire:key="target-{{ $index }}-{{ $target['type'] }}" wire:model="targets.{{ $index }}.value">
                                        <flux:select.option value="">{{ __('Choisir…') }}</flux:select.option>
                                        @if ($type === 'percent' && in_array($target['type'], ['department', 'category'], true))
                                            <flux:select.option value="all">{{ $target['type'] === 'department' ? __('Tous les départements') : __('Toutes les catégories') }}</flux:select.option>
                                        @endif
                                        @foreach ($targetOptions[$target['type']] ?? [] as $option)
                                            <flux:select.option :value="$option->id">{{ $option instanceof \App\Models\Category ? $option->department->name.' › '.$option->name : $option->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                                <flux:error name="targets.{{ $index }}.value" />
                            </div>
                            <flux:button type="button" variant="ghost" icon="x-mark" wire:click="removeTarget({{ $index }})" :label="__('Retirer')" />
                        </div>
                    @endforeach
                </div>

                <div class="mt-1 flex justify-end">
                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addTarget">{{ __('Ajouter') }}</flux:button>
                </div>
                <flux:error name="targets" />
            </div>

            <div class="flex items-center gap-2">
                <flux:checkbox wire:model="isActive" :label="__('Active')" />
                <flux:tooltip :content="__('Une promotion n\'est jamais supprimée : décochez « Active » pour la retirer.')">
                    <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                </flux:tooltip>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
