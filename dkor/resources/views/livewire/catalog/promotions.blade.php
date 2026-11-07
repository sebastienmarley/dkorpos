<div class="sm:p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading level="1" size="xl">{{ __('Promotions') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Rabais temporaires appliqués à l\'étape du paiement, une seule promotion par commande') }}</flux:text>
        </div>
        @can('promotions.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('Ajouter') }}</flux:button>
        @endcan
    </div>

    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Rechercher une promotion…')" />
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Promotion') }}</flux:table.column>
                <flux:table.column>{{ __('Rabais') }}</flux:table.column>
                <flux:table.column>{{ __('Période') }}</flux:table.column>
                <flux:table.column>{{ __('Produits visés') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($promotions as $promotion)
                    <flux:table.row :key="$promotion->id" @class(['opacity-50' => ! $promotion->is_active])>
                        <flux:table.cell variant="strong">
                            {{ $promotion->name }}
                            @if (! $promotion->is_active)
                                <flux:badge size="sm" class="ms-2">{{ __('Inactive') }}</flux:badge>
                            @elseif ($promotion->isInEffectOn(today()))
                                <flux:badge size="sm" color="green" class="ms-2">{{ __('En cours') }}</flux:badge>
                            @elseif (today()->isBefore($promotion->starts_on))
                                <flux:badge size="sm" color="blue" class="ms-2">{{ __('À venir') }}</flux:badge>
                            @else
                                <flux:badge size="sm" class="ms-2">{{ __('Terminée') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $promotion->discountLabel() }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $promotion->starts_on->format('Y-m-d') }} → {{ $promotion->ends_on->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="max-w-md truncate">{{ $promotion->targets->map->label()->join(', ') ?: '—' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            @can('promotions.edit')
                                <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEdit({{ $promotion->id }})" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="receipt-percent" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucune promotion.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-2xl">
        <flux:heading class="mb-1">{{ $editingId ? __('Modifier la promotion') : __('Nouvelle promotion') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="name" :placeholder="__('Nous payons les taxes, 10 % sur l\'éclairage…')" required autofocus />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Sorte de rabais') }}</flux:label>
                <flux:select wire:model.live="type">
                    @foreach ($promotionTypes as $promotionType)
                        <flux:select.option :value="$promotionType->value">{{ $promotionType->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="type" />
            </flux:field>

            @if ($type === 'percent')
                <flux:field class="max-w-xs">
                    <flux:label>{{ __('Rabais (%)') }}</flux:label>
                    <flux:input wire:model="percent" inputmode="decimal" />
                    <flux:error name="percent" />
                </flux:field>
            @elseif ($type === 'fixed_amount')
                <div class="flex flex-wrap gap-4">
                    <flux:field class="w-40">
                        <flux:label>{{ __('Rabais total') }}</flux:label>
                        <flux:input wire:model="amount" inputmode="decimal" />
                        <flux:error name="amount" />
                    </flux:field>
                    <flux:field class="w-40">
                        <flux:label>{{ __('À l\'achat de') }}</flux:label>
                        <flux:input wire:model="minimumQuantity" type="number" min="1" />
                        <flux:error name="minimumQuantity" />
                    </flux:field>
                </div>
                <flux:text class="text-sm text-zinc-500">{{ __('Le rabais est réparti sur les articles visés au prorata de leur prix.') }}</flux:text>
            @else
                <flux:text class="text-sm text-zinc-500">{{ __('Le prix des articles taxables visés est baissé pour que leur total, taxes incluses, égale le prix avant taxes.') }}</flux:text>
            @endif

            <div class="flex flex-wrap gap-4">
                <flux:field class="w-44">
                    <flux:label>{{ __('Du') }}</flux:label>
                    <flux:input wire:model="startsOn" type="date" required />
                    <flux:error name="startsOn" />
                </flux:field>
                <flux:field class="w-44">
                    <flux:label>{{ __('Au') }}</flux:label>
                    <flux:input wire:model="endsOn" type="date" required />
                    <flux:error name="endsOn" />
                </flux:field>
            </div>

            <div>
                <flux:heading size="sm" class="mb-2">{{ __('Produits visés') }}</flux:heading>

                @php($targetOptions = $this->getTargetOptions())
                <div class="space-y-2">
                    @foreach ($targets as $index => $target)
                        <div wire:key="target-{{ $index }}" class="flex items-start gap-2">
                            <div class="w-40">
                                <flux:select wire:model.live="targets.{{ $index }}.type">
                                    <flux:select.option value="department">{{ __('Département') }}</flux:select.option>
                                    <flux:select.option value="category">{{ __('Catégorie') }}</flux:select.option>
                                    <flux:select.option value="supplier">{{ __('Fournisseur') }}</flux:select.option>
                                    <flux:select.option value="product">{{ __('Produit') }}</flux:select.option>
                                </flux:select>
                            </div>
                            <div class="flex-1">
                                @if ($target['type'] === 'product')
                                    <flux:input wire:model="targets.{{ $index }}.value" :placeholder="__('Modèle du produit')" />
                                @else
                                    <flux:select wire:model="targets.{{ $index }}.value">
                                        <flux:select.option value="">{{ __('Choisir…') }}</flux:select.option>
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

                <div class="mt-1 flex items-center justify-between text-xs text-zinc-400">
                    <span>{{ __('Un article est visé si son département, sa catégorie, son fournisseur ou son modèle figure ici.') }}</span>
                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addTarget">{{ __('Ajouter') }}</flux:button>
                </div>
                <flux:error name="targets" />
            </div>

            <flux:checkbox wire:model="isActive" :label="__('Active')" />
            <flux:text class="text-sm text-zinc-500">{{ __('Une promotion n\'est jamais supprimée : décochez « Active » pour la retirer.') }}</flux:text>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
