<div class="sm:p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('products.index')" wire:navigate size="sm">
                {{ __('Retour') }}
            </flux:button>

            <div class="min-w-0">
                <flux:heading level="1" size="xl">{{ $product->model }}</flux:heading>
                <div class="mt-1 flex items-center gap-2">
                    <flux:text class="text-zinc-500">{{ $product->supplier->name }}</flux:text>
                    @if ($product->is_discontinued)
                        <flux:badge color="red" size="sm">{{ __('Discontinué') }}</flux:badge>
                    @endif
                    @if ($product->is_non_orderable)
                        <flux:badge color="zinc" size="sm">{{ __('Non commandable') }}</flux:badge>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex flex-shrink-0 items-center gap-3">
            <x-label-button :product="$product" />

            {{-- Prix de vente calculé --}}
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-5 py-3 text-right dark:border-zinc-700 dark:bg-zinc-800">
                <flux:text class="text-xs text-zinc-400">{{ __('Prix de vente') }}</flux:text>
                <div class="mt-0.5 text-2xl font-semibold text-zinc-900 dark:text-white">
                    @php $sp = $this->getSellingPrice(); @endphp
                    {{ $sp->cents() % 100 === 0 ? number_format(intdiv($sp->cents(), 100)).' $' : $sp->format() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Onglets --}}
    <div x-data="{ tab: @entangle('activeTab') }">
        <div class="flex overflow-x-auto overflow-y-hidden border-b border-zinc-200 [scrollbar-width:none] dark:border-zinc-700">
            <button
                type="button"
                @click="$wire.switchTab('general')"
                :class="tab === 'general' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
            >{{ __('Général') }}</button>

            <button
                type="button"
                @click="$wire.switchTab('description')"
                :class="tab === 'description' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
            >{{ __('Description') }}</button>

            <button
                type="button"
                @click="$wire.switchTab('inventory')"
                :class="tab === 'inventory' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
            >{{ __('Inventaire') }}</button>

            <button
                type="button"
                @click="$wire.switchTab('photos')"
                :class="tab === 'photos' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
            >{{ __('Photos') }}</button>

            @can('parts.view')
                <button
                    type="button"
                    @click="$wire.switchTab('parts')"
                    :class="tab === 'parts' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
                >{{ __('Pièces') }} ({{ $parts->count() }})</button>
            @endcan
        </div>

        {{-- Général --}}
        <div x-show="tab === 'general'" x-cloak>
            <form wire:submit="saveAndClose" class="mt-6 max-w-2xl space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Fournisseur') }}</flux:label>
                        <flux:select wire:model.live="supplierId">
                            <flux:select.option value="">{{ __('Sélectionner…') }}</flux:select.option>
                            @foreach ($this->getSuppliers() as $supplier)
                                <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="supplierId" />
                    </flux:field>

                    <flux:field>
                        <div class="flex items-center gap-1.5">
                            <flux:label>{{ __('Modèle fournisseur') }}</flux:label>
                            <flux:tooltip content="{{ $supplierModelLocked ? __('Verrouillé : le produit est dans une liste de prix.') : __('Référence utilisée par le fournisseur et dans les commandes.') }}">
                                <flux:icon name="information-circle" class="h-4 w-4 text-zinc-400" />
                            </flux:tooltip>
                        </div>
                        <flux:input wire:model="supplierModel" type="text" required :disabled="$supplierModelLocked" />
                        <flux:error name="supplierModel" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Modèle') }}</flux:label>
                    <flux:input wire:model="model" type="text" required />
                    <input type="hidden" wire:model="cleanModel" />
                    <flux:error name="model" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Département') }}</flux:label>
                        <flux:select wire:model.live="departmentId">
                            <flux:select.option value="">{{ __('Aucun') }}</flux:select.option>
                            @foreach ($this->getDepartments() as $department)
                                <flux:select.option :value="$department->id">{{ $department->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="departmentId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Catégorie') }}</flux:label>
                        <flux:select wire:model="categoryId">
                            <flux:select.option value="">{{ __('Aucune') }}</flux:select.option>
                            @foreach ($this->getCategories() as $category)
                                <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="categoryId" />
                    </flux:field>
                </div>

                <x-cost-input wire:model.live="cost" label="{{ __('Coût') }}" name="cost" />

                <div class="flex gap-6">
                    <flux:field>
                        <flux:checkbox wire:model="isDiscontinued" :label="__('Discontinué')" />
                        <flux:error name="isDiscontinued" />
                    </flux:field>

                    <flux:field>
                        <flux:checkbox wire:model="isNonOrderable" :label="__('Non commandable')" />
                        <flux:error name="isNonOrderable" />
                        <flux:checkbox wire:model="isTaxable" :label="__('Taxable (TPS/TVQ)')" class="mt-2" />
                        <flux:error name="isTaxable" />
                        <flux:checkbox wire:model="isCustom" :label="__('Sur mesure')" :description="__('Gabarit : chaque vente saisit ses spécifications, le coût soumis et le prix.')" class="mt-2" />
                        <flux:error name="isCustom" />
                    </flux:field>
                </div>

                <div class="flex justify-end pt-2">
                    @can('products.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder et fermer') }}</flux:button>
                    @endcan
                </div>
            </form>
        </div>

        {{-- Description --}}
        <div x-show="tab === 'description'" x-cloak>
            <form wire:submit="saveAndClose" class="mt-6 max-w-2xl space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Couleur') }}</flux:label>
                        <flux:select wire:model="colorId">
                            <flux:select.option value="">{{ __('Aucune') }}</flux:select.option>
                            @foreach ($this->getColors() as $color)
                                <flux:select.option :value="$color->id">
                                    {{ $color->name }}{{ $color->hex_code ? ' ('.$color->hex_code.')' : '' }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="colorId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Style') }}</flux:label>
                        <flux:select wire:model="styleId">
                            <flux:select.option value="">{{ __('Aucun') }}</flux:select.option>
                            @foreach ($this->getStyles() as $style)
                                <flux:select.option :value="$style->id">{{ $style->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="styleId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Collection') }}</flux:label>
                        <flux:input wire:model="collection" type="text" />
                        <flux:error name="collection" />
                    </flux:field>
                </div>

                <flux:field class="max-w-xs">
                    <flux:label>{{ __('IMAP ($)') }}</flux:label>
                    <flux:input wire:model="imap" type="number" step="0.01" min="0" placeholder="0.00" />
                    <flux:error name="imap" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="description" rows="8" />
                    <flux:error name="description" />
                </flux:field>

                <div>
                    <flux:heading size="sm" class="mb-4">{{ __('Dimensions') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-4">
                        <flux:field>
                            <flux:label>{{ __('Longueur') }}</flux:label>
                            <flux:input wire:model="length" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="length" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Largeur') }}</flux:label>
                            <flux:input wire:model="width" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="width" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Hauteur') }}</flux:label>
                            <flux:input wire:model="height" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="height" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Poids') }}</flux:label>
                            <flux:input wire:model="weight" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="weight" />
                        </flux:field>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    @can('products.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder et fermer') }}</flux:button>
                    @endcan
                </div>
            </form>

            @php($locationsByStore = $this->getLocationsByStore())
            @if ($locationsByStore->isNotEmpty())
                <div class="mt-8 max-w-2xl">
                    <flux:heading size="sm" class="mb-1">{{ __('Emplacement habituel dans l\'entrepôt') }}</flux:heading>
                    <flux:text class="mb-3 text-sm text-zinc-500">{{ __('Proposé par défaut quand on range ce produit pour un client.') }}</flux:text>
                    <div class="space-y-2">
                        @foreach ($locationsByStore as $entry)
                            <div wire:key="habitual-{{ $entry['store']->id }}" class="flex flex-wrap items-end gap-2">
                                <flux:field class="min-w-48 flex-1">
                                    <flux:label>{{ $entry['store']->name }}</flux:label>
                                    <flux:select wire:model="habitualLocations.{{ $entry['store']->id }}" :disabled="! auth()->user()->can('products.edit')">
                                        <flux:select.option value="">{{ __('— Aucun —') }}</flux:select.option>
                                        @foreach ($entry['locations'] as $location)
                                            <flux:select.option :value="$location->id">{{ $location->label() }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="habitualLocations.{{ $entry['store']->id }}" />
                                </flux:field>
                                @can('products.edit')
                                    <flux:button wire:click="saveHabitualLocation({{ $entry['store']->id }})">{{ __('Enregistrer') }}</flux:button>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- UPC --}}
        <div x-show="tab === 'description'" x-cloak class="mt-8 max-w-2xl">
            <flux:heading size="sm" class="mb-3">{{ __('UPC') }}</flux:heading>
            <div class="mb-3 flex flex-wrap gap-2">
                @forelse ($upcs as $upc)
                    <flux:badge wire:key="upc-{{ $upc->id }}" class="font-mono">
                        {{ $upc->upc }}
                        @can('products.edit')
                            <button type="button" wire:click="removeUpc({{ $upc->id }})" class="ml-1 text-zinc-400 hover:text-red-500" aria-label="{{ __('Retirer') }}">&times;</button>
                        @endcan
                    </flux:badge>
                @empty
                    <flux:text class="text-zinc-400">{{ __('Aucun UPC.') }}</flux:text>
                @endforelse
            </div>
            @can('products.edit')
                <form wire:submit="addUpc" class="flex max-w-sm items-start gap-2">
                    <flux:field class="flex-1">
                        <flux:input wire:model="newUpc" type="text" :placeholder="__('Ajouter un UPC')" />
                        <flux:error name="newUpc" />
                    </flux:field>
                    <flux:button type="submit">{{ __('Ajouter') }}</flux:button>
                </form>
            @endcan
        </div>

        {{-- Pièces --}}
        @can('parts.view')
            <div x-show="tab === 'parts'" x-cloak class="mt-6 max-w-2xl">
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($parts as $part)
                        <div wire:key="part-{{ $part->id }}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                            <span class="min-w-0">
                                <flux:link :href="route('parts.show', $part)" wire:navigate class="font-medium">{{ $part->model }}</flux:link>
                                <span class="text-zinc-500"> · {{ $part->description }}</span>
                                <span class="block text-xs text-zinc-400">{{ $part->supplier->name }}</span>
                            </span>
                            <span class="flex-shrink-0 text-zinc-500">{{ $part->last_cost->format() }}</span>
                        </div>
                    @empty
                        <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucune pièce liée à ce produit.') }}</div>
                    @endforelse
                </div>
            </div>
        @endcan

        {{-- Photos --}}
        {{-- Inventaire par magasin (ADR 0012) --}}
        <div x-show="tab === 'inventory'" x-cloak class="mt-6">
            @php($stockStatuses = [\App\Enums\InventoryStatus::InStock, \App\Enums\InventoryStatus::InDemo, \App\Enums\InventoryStatus::ReservedCustomer, \App\Enums\InventoryStatus::InDelivery, \App\Enums\InventoryStatus::DefectiveStock, \App\Enums\InventoryStatus::Lost, \App\Enums\InventoryStatus::OnOrder])
            @php($stocks = $product->inventoryStocks()->with('store')->get()->sortBy(fn ($stock) => $stock->store?->name))
            <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Magasin') }}</flux:table.column>
                        @foreach ($stockStatuses as $stockStatus)
                            <flux:table.column align="end">{{ $stockStatus->label() }}</flux:table.column>
                        @endforeach
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($stocks as $stock)
                            <flux:table.row :key="'stock-'.$stock->id">
                                <flux:table.cell variant="strong">{{ $stock->store?->name ?? '—' }}</flux:table.cell>
                                @foreach ($stockStatuses as $stockStatus)
                                    <flux:table.cell align="end">{{ $stock->quantityFor($stockStatus) }}</flux:table.cell>
                                @endforeach
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell :colspan="count($stockStatuses) + 1" class="py-6 text-center text-zinc-400">{{ __('Aucun stock dans les magasins.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                        @if ($stocks->count() > 1)
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Total de l\'entreprise') }}</flux:table.cell>
                                @foreach ($stockStatuses as $stockStatus)
                                    <flux:table.cell align="end" class="font-semibold">{{ $stocks->sum(fn ($stock) => $stock->quantityFor($stockStatus)) }}</flux:table.cell>
                                @endforeach
                            </flux:table.row>
                        @endif
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>

        <div x-show="tab === 'photos'" x-cloak>
            <div class="mt-6 max-w-2xl">
                <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 bg-zinc-50 py-16 dark:border-zinc-600 dark:bg-zinc-800/50">
                    <flux:icon name="photo" class="mb-3 h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading level="3" class="text-zinc-500">{{ __('Gestion des photos') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-400">{{ __('La gestion des images sera disponible prochainement.') }}</flux:text>
                </div>
            </div>
        </div>
    </div>
</div>
