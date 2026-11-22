<div class="sm:p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <flux:heading level="1" size="xl">{{ __('Produits') }}</flux:heading>
            <flux:tooltip :content="__('Liste de tous les produits')">
                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
            </flux:tooltip>
        </div>

        @can('products.create')
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-product-create')">
                {{ __('Ajouter un produit') }}
            </flux:button>
        @endcan
    </div>

    {{-- Filtres --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <div class="min-w-64 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('ID, modèle ou modèle fournisseur…') }}" icon="magnifying-glass" />
        </div>

        <div class="w-full sm:w-56">
            <flux:select wire:model.live="supplierId">
                <flux:select.option value="">{{ __('Tous les fournisseurs') }}</flux:select.option>
                @foreach ($suppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full sm:w-48">
            <flux:select wire:model.live="availabilityFilter">
                <flux:select.option value="">{{ __('Toute disponibilité') }}</flux:select.option>
                @foreach ($availabilityOptions as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Tableau --}}
    @if ($products->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table :paginate="$products">
                <flux:table.columns>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Modèle') }}</flux:table.column>
                    <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                    <flux:table.column>{{ __('Prix de vente') }}</flux:table.column>
                    <flux:table.column align="end">
                        <span class="inline-flex items-center gap-1.5">
                            {{ __('Stock') }}
                            <flux:tooltip :content="auth()->user()->hasAllStores() ? __('En stock dans tous les magasins.') : __('En stock dans le magasin qui sert vos ventes.')">
                                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                            </flux:tooltip>
                        </span>
                    </flux:table.column>
                    <flux:table.column align="end">
                        <span class="inline-flex items-center gap-1.5">
                            {{ __('Réservé') }}
                            <flux:tooltip :content="__('Réservé pour des clients, et en commande pour des clients.')">
                                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                            </flux:tooltip>
                        </span>
                    </flux:table.column>
                    <flux:table.column />
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($products as $product)
                        <x-table.row-link :key="$product->id" :href="route('products.show', $product)">
                            <flux:table.cell class="tabular-nums text-zinc-500">{{ $product->id }}</flux:table.cell>

                            <flux:table.cell variant="strong">{{ $product->model }}</flux:table.cell>

                            <flux:table.cell>
                                <div class="max-w-32 truncate lg:max-w-44" title="{{ $product->supplier->name }}">
                                    @can('suppliers.view')
                                        <flux:link :href="route('suppliers.show', $product->supplier)" wire:navigate>{{ $product->supplier->name }}</flux:link>
                                    @else
                                        {{ $product->supplier->name }}
                                    @endcan
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>{{ $product->selling_price->format() }}</flux:table.cell>

                            <flux:table.cell align="end">
                                <div class="flex flex-wrap items-center justify-end gap-1">
                                    @if ($product->on_clearance)
                                        <flux:badge size="sm" color="amber">{{ __('Liquidation') }}</flux:badge>
                                    @endif
                                    @if ((int) $product->stock_in_demo > 0)
                                        <flux:badge size="sm" color="blue">{{ __('Démo :count', ['count' => (int) $product->stock_in_demo]) }}</flux:badge>
                                    @endif
                                    <span @class(['font-medium tabular-nums', 'text-zinc-400' => (int) $product->stock_in_stock === 0])>{{ (int) $product->stock_in_stock }}</span>
                                </div>
                                @if ((int) $product->stock_on_order > 0)
                                    <div class="text-xs text-zinc-400">{{ __('+:count en commande', ['count' => (int) $product->stock_on_order]) }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <span @class(['font-medium tabular-nums', 'text-zinc-400' => (int) $product->stock_reserved === 0])>{{ (int) $product->stock_reserved }}</span>
                                @if ((int) $product->stock_customer_order > 0)
                                    <div class="text-xs text-zinc-400">{{ __('+:count en commande', ['count' => (int) $product->stock_customer_order]) }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="text-right">
                                <x-label-button :product="$product" :label="false" variant="ghost" size="sm" />
                            </flux:table.cell>
                        </x-table.row-link>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="mt-3">
            <flux:text class="text-sm text-zinc-400">
                {{ trans_choice(':count produit|:count produits', $products->total()) }}
            </flux:text>
        </div>
    @elseif ($hasSearchCriteria)
        <div class="flex flex-col items-center gap-2 py-12 text-center">
            <flux:icon name="cube" class="h-8 w-8 text-zinc-300" />
            <flux:text class="text-zinc-400">{{ __('Aucun produit trouvé pour ces critères.') }}</flux:text>
        </div>
    @endif

    <livewire:product-form />
</div>
