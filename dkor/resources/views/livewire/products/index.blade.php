<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Produits') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Liste de tous les produits') }}</flux:text>
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
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher un produit…') }}" icon="magnifying-glass" />
        </div>

        <div class="w-56">
            <flux:select wire:model.live="supplierId">
                <flux:select.option value="">{{ __('Tous les fournisseurs') }}</flux:select.option>
                @foreach ($suppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-48">
            <flux:select wire:model.live="departmentId">
                <flux:select.option value="">{{ __('Tous les départements') }}</flux:select.option>
                @foreach ($this->getDepartments() as $department)
                    <flux:select.option :value="$department->id">{{ $department->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-48">
            <flux:select wire:model.live="categoryId" :disabled="blank($departmentId)">
                <flux:select.option value="">{{ __('Toutes les catégories') }}</flux:select.option>
                @foreach ($this->getCategories() as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$products">
            <flux:table.columns>
                <flux:table.column>{{ __('Modèle') }}</flux:table.column>
                <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                <flux:table.column>{{ __('Classification') }}</flux:table.column>
                <flux:table.column>{{ __('Coût') }}</flux:table.column>
                <flux:table.column>{{ __('Prix de vente') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($products as $product)
                    <flux:table.row :key="$product->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('products.show', $product)" wire:navigate>
                                {{ $product->model }}
                            </flux:link>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:link :href="route('suppliers.show', $product->supplier)" wire:navigate>
                                {{ $product->supplier->name }}
                            </flux:link>
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500">
                            @if ($product->category)
                                {{ $product->category->department->name }} › {{ $product->category->name }}
                            @else
                                —
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $product->cost->format() }}</flux:table.cell>

                        <flux:table.cell>{{ $product->selling_price->format() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="cube" class="h-8 w-8 text-zinc-300" />
                                @if (filled($search) || filled($supplierId) || filled($departmentId))
                                    <flux:text class="text-zinc-400">{{ __('Aucun produit trouvé pour ces critères.') }}</flux:text>
                                @else
                                    <flux:text class="text-zinc-400">{{ __('Aucun produit. Cliquez sur « Ajouter » pour commencer.') }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-3 flex items-center justify-between">
        <flux:text class="text-sm text-zinc-400">
            {{ trans_choice(':count produit|:count produits', $products->total()) }}
        </flux:text>
    </div>

    <livewire:product-form />
</div>
