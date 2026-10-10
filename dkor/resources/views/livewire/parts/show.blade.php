<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('parts.index')" wire:navigate size="sm">
                {{ __('Retour') }}
            </flux:button>

            <div class="min-w-0">
                <flux:heading level="1" size="xl">{{ $part->model }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500">{{ $part->description }} · {{ $part->supplier->name }}</flux:text>
            </div>
        </div>

        {{-- Prix de vente calculé --}}
        <div class="flex-shrink-0 rounded-xl border border-zinc-200 bg-zinc-50 px-5 py-3 text-right dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-xs text-zinc-400">{{ __('Prix de vente') }}</flux:text>
            <div class="mt-0.5 text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($part->selling_price, 2) }} $</div>
        </div>
    </div>

    <div class="grid max-w-5xl gap-8 lg:grid-cols-2">
        {{-- Fiche --}}
        <div>
            <flux:heading size="sm" class="mb-4">{{ __('Fiche') }}</flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Fournisseur') }}</flux:label>
                    <flux:select wire:model="supplierId" :disabled="auth()->user()->cannot('parts.edit')">
                        @foreach ($this->getSuppliers() as $supplier)
                            <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="supplierId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Modèle') }}</flux:label>
                    <flux:input wire:model="model" type="text" required :disabled="auth()->user()->cannot('parts.edit')" />
                    <flux:error name="model" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:input wire:model="description" type="text" required :disabled="auth()->user()->cannot('parts.edit')" />
                    <flux:error name="description" />
                </flux:field>

                <x-cost-input wire:model="lastCost" label="{{ __('Dernier coût') }}" name="lastCost" :disabled="auth()->user()->cannot('parts.edit')" />

                @can('parts.edit')
                    <div class="flex justify-end pt-2">
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    </div>
                @endcan
            </form>
        </div>

        {{-- Produits liés --}}
        <div>
            <flux:heading size="sm" class="mb-1">{{ __('Produits') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Produits que cette pièce permet de réparer.') }}</flux:text>

            <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @forelse ($products as $product)
                    <div wire:key="linked-product-{{ $product->id }}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                        <span class="min-w-0">
                            <flux:link :href="route('products.show', $product)" wire:navigate class="font-medium">{{ $product->model }}</flux:link>
                            <span class="block text-xs text-zinc-400">{{ $product->supplier->name }}</span>
                        </span>
                        @can('parts.edit')
                            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="detachProduct({{ $product->id }})" :aria-label="__('Retirer')" />
                        @endcan
                    </div>
                @empty
                    <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun produit lié.') }}</div>
                @endforelse
            </div>

            @can('parts.edit')
                <div class="mt-4">
                    <flux:input wire:model.live.debounce.300ms="productSearch" icon="magnifying-glass" :placeholder="__('Lier un produit : modèle ou modèle fournisseur…')" />

                    @if (mb_strlen(trim($productSearch)) >= 2)
                        <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                            @forelse ($this->getProductResults() as $result)
                                <button type="button" wire:key="product-result-{{ $result->id }}" wire:click="attachProduct({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <span class="min-w-0">
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $result->model }}</span>
                                        <span class="block text-xs text-zinc-400">{{ $result->supplier->name }}</span>
                                    </span>
                                    <flux:icon name="plus" class="h-4 w-4 flex-shrink-0 text-zinc-400" />
                                </button>
                            @empty
                                <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun produit trouvé.') }}</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endcan
        </div>
    </div>
</div>
