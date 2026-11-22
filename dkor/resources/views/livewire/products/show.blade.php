<div class="sm:p-6">
    @php
        $canEditProduct = auth()->user()->can('products.edit');
        $sellingPrice = $this->getSellingPrice();
        $tabs = array_filter([
            'general' => __('Général'),
            'description' => __('Description'),
            'inventory' => __('Inventaire'),
            'photos' => __('Photos'),
            'parts' => auth()->user()->can('parts.view') ? __('Pièces').' ('.$parts->count().')' : null,
        ]);
        $card = 'rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6 dark:border-zinc-700 dark:bg-zinc-900';
    @endphp

    {{-- En-tête --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('products.index')" wire:navigate size="sm" :aria-label="__('Retour')" />

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading level="1" size="xl" class="break-all">{{ $product->model }}</flux:heading>
                    <flux:badge size="sm" color="zinc" class="tabular-nums">#{{ $product->id }}</flux:badge>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <flux:text class="text-zinc-500">{{ $product->supplier->name }}</flux:text>
                    @if ($product->is_discontinued)
                        <flux:badge color="red" size="sm">{{ __('Discontinué') }}</flux:badge>
                    @endif
                    @if ($product->is_non_orderable)
                        <flux:badge color="zinc" size="sm">{{ __('Non commandable') }}</flux:badge>
                    @endif
                    @if ($product->is_custom)
                        <flux:badge color="blue" size="sm">{{ __('Sur mesure') }}</flux:badge>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 max-sm:w-full max-sm:justify-between">
            <x-label-button :product="$product" />

            <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-5 py-3 text-right dark:border-zinc-700 dark:bg-zinc-800">
                <flux:text class="text-xs text-zinc-400">{{ __('Prix de vente') }}</flux:text>
                <div class="mt-0.5 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                    {{ $sellingPrice->cents() % 100 === 0 ? number_format(intdiv($sellingPrice->cents(), 100)).' $' : $sellingPrice->format() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Onglets --}}
    <div x-data="{ tab: @entangle('activeTab') }">
        <x-tabs :tabs="$tabs" />

        {{-- Général --}}
        <div x-show="tab === 'general'" x-cloak>
            <form wire:submit="saveAndClose" class="mt-6 space-y-6">
                <section class="{{ $card }}">
                    <flux:heading size="sm" class="mb-4">{{ __('Identification') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                            <flux:label class="gap-1.5">
                                {{ __('Modèle fournisseur') }}
                                <flux:tooltip :content="$supplierModelLocked ? __('Verrouillé : le produit est dans une liste de prix.') : __('Référence utilisée par le fournisseur et dans les commandes.')">
                                    <flux:icon name="{{ $supplierModelLocked ? 'lock-closed' : 'information-circle' }}" variant="mini" class="text-zinc-400" />
                                </flux:tooltip>
                            </flux:label>
                            <flux:input wire:model="supplierModel" type="text" required :disabled="$supplierModelLocked" />
                            <flux:error name="supplierModel" />
                        </flux:field>

                        <flux:field class="sm:col-span-2 lg:col-span-1">
                            <flux:label>{{ __('Modèle') }}</flux:label>
                            <flux:input wire:model="model" type="text" required />
                            <input type="hidden" wire:model="cleanModel" />
                            <flux:error name="model" />
                        </flux:field>
                    </div>
                </section>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="{{ $card }}">
                        <flux:heading size="sm" class="mb-4">{{ __('Classification') }}</flux:heading>
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
                    </section>

                    <section class="{{ $card }}">
                        <flux:heading size="sm" class="mb-4">{{ __('Prix') }}</flux:heading>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-cost-input wire:model.live="cost" label="{{ __('Coût') }}" name="cost" />

                            <flux:field>
                                <flux:label class="gap-1.5">
                                    {{ __('Prix de vente') }}
                                    <flux:tooltip :content="__('Calculé à partir du coût et du multiplicateur du fournisseur.')">
                                        <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                                    </flux:tooltip>
                                </flux:label>
                                <flux:input :value="$sellingPrice->format()" readonly class:input="tabular-nums" />
                            </flux:field>
                        </div>
                    </section>
                </div>

                <section class="{{ $card }}">
                    <flux:heading size="sm" class="mb-4">{{ __('Statut') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <flux:field>
                            <flux:checkbox wire:model="isTaxable" :label="__('Taxable (TPS/TVQ)')" />
                            <flux:error name="isTaxable" />
                        </flux:field>

                        <flux:field>
                            <flux:checkbox wire:model="isDiscontinued" :label="__('Discontinué')" />
                            <flux:error name="isDiscontinued" />
                        </flux:field>

                        <flux:field>
                            <flux:checkbox wire:model="isNonOrderable" :label="__('Non commandable')" />
                            <flux:error name="isNonOrderable" />
                        </flux:field>

                        <flux:field>
                            <div class="flex items-center gap-2">
                                <flux:checkbox wire:model="isCustom" :label="__('Sur mesure')" />
                                <flux:tooltip :content="__('Gabarit : chaque vente saisit ses spécifications, le coût soumis et le prix.')">
                                    <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                                </flux:tooltip>
                            </div>
                            <flux:error name="isCustom" />
                        </flux:field>
                    </div>
                </section>

                @if ($canEditProduct)
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" class="max-sm:w-full">{{ __('Sauvegarder et fermer') }}</flux:button>
                    </div>
                @endif
            </form>
        </div>

        {{-- Description --}}
        <div x-show="tab === 'description'" x-cloak class="mt-6 space-y-6">
            <form wire:submit="saveAndClose" class="space-y-6">
                <section class="{{ $card }}">
                    <flux:heading size="sm" class="mb-4">{{ __('Apparence') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

                        <flux:field>
                            <flux:label>{{ __('IMAP ($)') }}</flux:label>
                            <flux:input wire:model="imap" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="imap" />
                        </flux:field>

                        <flux:field class="sm:col-span-2 lg:col-span-4">
                            <flux:label>{{ __('Description') }}</flux:label>
                            <flux:textarea wire:model="description" rows="6" />
                            <flux:error name="description" />
                        </flux:field>
                    </div>
                </section>

                <section class="{{ $card }}">
                    <flux:heading size="sm" class="mb-4">{{ __('Dimensions') }}</flux:heading>
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        @foreach (['length' => __('Longueur'), 'width' => __('Largeur'), 'height' => __('Hauteur'), 'weight' => __('Poids')] as $dimension => $dimensionLabel)
                            <flux:field wire:key="dimension-{{ $dimension }}">
                                <flux:label>{{ $dimensionLabel }}</flux:label>
                                <flux:input wire:model="{{ $dimension }}" type="number" step="0.01" min="0" placeholder="0.00" />
                                <flux:error name="{{ $dimension }}" />
                            </flux:field>
                        @endforeach
                    </div>
                </section>

                @if ($canEditProduct)
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" class="max-sm:w-full">{{ __('Sauvegarder et fermer') }}</flux:button>
                    </div>
                @endif
            </form>

            @php
                $locationsByStore = $this->getLocationsByStore();
            @endphp
            <div @class(['grid gap-6', 'lg:grid-cols-2' => $locationsByStore->isNotEmpty()])>
                {{-- UPC --}}
                <section class="{{ $card }}">
                    <flux:heading size="sm" class="mb-4">{{ __('UPC') }}</flux:heading>
                    <div class="mb-3 flex flex-wrap gap-2">
                        @forelse ($upcs as $upc)
                            <flux:badge wire:key="upc-{{ $upc->id }}" class="font-mono">
                                {{ $upc->upc }}
                                @if ($canEditProduct)
                                    <button type="button" wire:click="removeUpc({{ $upc->id }})" class="ml-1 text-zinc-400 hover:text-red-500" aria-label="{{ __('Retirer') }}">&times;</button>
                                @endif
                            </flux:badge>
                        @empty
                            <flux:text class="text-zinc-400">{{ __('Aucun UPC.') }}</flux:text>
                        @endforelse
                    </div>
                    @if ($canEditProduct)
                        <form wire:submit="addUpc" class="flex items-start gap-2">
                            <flux:field class="flex-1">
                                <flux:input wire:model="newUpc" type="text" :placeholder="__('Ajouter un UPC')" />
                                <flux:error name="newUpc" />
                            </flux:field>
                            <flux:button type="submit">{{ __('Ajouter') }}</flux:button>
                        </form>
                    @endif
                </section>

                @if ($locationsByStore->isNotEmpty())
                    <section class="{{ $card }}">
                        <div class="mb-4 flex items-center gap-1.5">
                            <flux:heading size="sm">{{ __('Emplacement habituel dans l\'entrepôt') }}</flux:heading>
                            <flux:tooltip :content="__('Proposé par défaut quand on range ce produit pour un client.')">
                                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                            </flux:tooltip>
                        </div>
                        <div class="space-y-3">
                            @foreach ($locationsByStore as $entry)
                                <div wire:key="habitual-{{ $entry['store']->id }}" class="flex items-end gap-2">
                                    <flux:field class="min-w-0 flex-1">
                                        <flux:label>{{ $entry['store']->name }}</flux:label>
                                        <flux:select wire:model="habitualLocations.{{ $entry['store']->id }}" :disabled="! $canEditProduct">
                                            <flux:select.option value="">{{ __('— Aucun —') }}</flux:select.option>
                                            @foreach ($entry['locations'] as $location)
                                                <flux:select.option :value="$location->id">{{ $location->label() }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                        <flux:error name="habitualLocations.{{ $entry['store']->id }}" />
                                    </flux:field>
                                    @if ($canEditProduct)
                                        <flux:button wire:click="saveHabitualLocation({{ $entry['store']->id }})">{{ __('Enregistrer') }}</flux:button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </div>

        {{-- Pièces --}}
        @can('parts.view')
            <div x-show="tab === 'parts'" x-cloak class="mt-6">
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                    @forelse ($parts as $part)
                        <div wire:key="part-{{ $part->id }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span class="min-w-0">
                                <flux:link :href="route('parts.show', $part)" wire:navigate class="font-medium">{{ $part->model }}</flux:link>
                                <span class="text-zinc-500"> · {{ $part->description }}</span>
                                <span class="block text-xs text-zinc-400">{{ $part->supplier->name }}</span>
                            </span>
                            <span class="flex-shrink-0 text-zinc-500">{{ $part->last_cost->format() }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-zinc-400">{{ __('Aucune pièce liée à ce produit.') }}</div>
                    @endforelse
                </div>
            </div>
        @endcan

        {{-- Photos --}}
        {{-- Inventaire par magasin (ADR 0012) --}}
        <div x-show="tab === 'inventory'" x-cloak class="mt-6">
            @php
                $stockStatuses = [\App\Enums\InventoryStatus::InStock, \App\Enums\InventoryStatus::InDemo, \App\Enums\InventoryStatus::ReservedCustomer, \App\Enums\InventoryStatus::InDelivery, \App\Enums\InventoryStatus::DefectiveStock, \App\Enums\InventoryStatus::Lost, \App\Enums\InventoryStatus::OnOrder];
                $stocks = $product->inventoryStocks()->with('store')->get()->sortBy(fn ($stock) => $stock->store?->name);
            @endphp
            @can('inventory.view')
                <div class="mb-3 flex justify-end">
                    <flux:button size="sm" variant="ghost" icon="clipboard-document-list" :href="route('inventory.movements', ['product' => $product->id])" wire:navigate>
                        {{ __('Voir les mouvements') }}
                    </flux:button>
                </div>
            @endcan
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

            <livewire:products.demos-and-clearances :product="$product" />
        </div>

        <div x-show="tab === 'photos'" x-cloak>
            <div class="mt-6">
                <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 bg-zinc-50 py-16 dark:border-zinc-600 dark:bg-zinc-800/50">
                    <flux:icon name="photo" class="mb-3 h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading level="3" class="text-zinc-500">{{ __('Gestion des photos') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-400">{{ __('La gestion des images sera disponible prochainement.') }}</flux:text>
                </div>
            </div>
        </div>
    </div>
</div>
