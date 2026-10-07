<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Commandes clients') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Commandes de vente aux clients') }}</flux:text>
        </div>

        @can('customers.view')
            <flux:button variant="primary" icon="user-plus" wire:click="openCustomerModal">
                {{ __('Ajouter un client') }}
            </flux:button>
        @endcan
    </div>

    {{-- Produits --}}
    <div class="mb-6">
        <flux:button icon="plus" wire:click="openProductModal">{{ __('Ajouter un produit') }}</flux:button>

        @if ($products->isNotEmpty())
            <div class="mt-3 divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                @foreach ($products as $product)
                    <div wire:key="product-{{ $product->id }}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                        <span class="min-w-0">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $product->model }}</span>
                            <span class="text-zinc-500"> · {{ $product->supplier->name }} · #{{ $product->id }}</span>
                        </span>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeProduct({{ $product->id }})" :label="__('Retirer')" />
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Client sélectionné --}}
    @if ($selectedCustomer)
        <div class="flex max-w-md items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center gap-2">
                <flux:icon.user class="h-4 w-4 text-zinc-400" />
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $selectedCustomer->firstname }} {{ $selectedCustomer->lastname }}</span>
            </div>
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearCustomer" :label="__('Retirer')" />
        </div>
    @endif

    {{-- Recherche de client --}}
    <flux:modal wire:model="showCustomerModal" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Ajouter un client') }}</flux:heading>

        <div class="mt-6">
            <flux:field>
                <flux:label>{{ __('Client') }}</flux:label>

                <flux:input
                    wire:model.live.debounce.300ms="customerSearch"
                    placeholder="{{ __('Rechercher un client…') }}"
                    icon="magnifying-glass"
                    autofocus
                />

                @if ($customerResults->isNotEmpty())
                    <div class="mt-1 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($customerResults as $c)
                            <button
                                type="button"
                                wire:click="selectCustomer({{ $c->id }})"
                                class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            >
                                <flux:avatar :name="$c->firstname.' '.$c->lastname" size="sm" />
                                <div>
                                    <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $c->firstname }} {{ $c->lastname }}</div>
                                    @if ($c->phone || $c->cellphone)
                                        <div class="text-xs text-zinc-400">{{ $c->phone ?: $c->cellphone }}</div>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen($customerSearch) >= 4)
                    <div class="mt-1 flex items-center justify-between rounded-lg border border-dashed border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <flux:text class="text-sm text-zinc-400">{{ __('Aucun client trouvé.') }}</flux:text>
                        @can('customers.create')
                            <flux:button size="sm" variant="ghost" icon="user-plus" x-on:click="$dispatch('open-customer-create')">
                                {{ __('Créer') }}
                            </flux:button>
                        @endcan
                    </div>
                @endif
            </flux:field>
        </div>
    </flux:modal>

    {{-- Recherche de produit --}}
    <flux:modal wire:model="showProductModal" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Ajouter un produit') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Fournisseur') }}</flux:label>
                <flux:select wire:model.live="productSupplierId">
                    <flux:select.option value="">{{ __('Tous — recherche par ID seulement') }}</flux:select.option>
                    @foreach ($this->getSuppliers() as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ filled($productSupplierId) ? __('Produit') : __('ID du produit') }}</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="productSearch"
                    icon="magnifying-glass"
                    :placeholder="filled($productSupplierId) ? __('ID, modèle ou collection…') : __('ID du produit…')"
                />

                @if (filled($productSearch))
                    <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @forelse ($this->getProductResults() as $result)
                            <button type="button" wire:key="product-result-{{ $result->id }}" wire:click="addProduct({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                <span class="min-w-0">
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $result->model }}</span>
                                    <span class="text-zinc-500"> · {{ $result->supplier->name }} · #{{ $result->id }}</span>
                                </span>
                            </button>
                        @empty
                            <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun produit trouvé.') }}</div>
                        @endforelse
                    </div>
                @endif
            </flux:field>

            @if (filled($productSupplierId))
                <flux:field>
                    <flux:label>{{ __('Chercher dans les listes de prix') }}</flux:label>
                    <flux:input wire:model.live.debounce.300ms="priceListSearch" icon="magnifying-glass" :placeholder="__('Modèle ou collection…')" />

                    @if (filled($priceListSearch))
                        <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                            @forelse ($this->getPriceListResults() as $result)
                                <button type="button" wire:key="price-list-{{ $result->id }}" wire:click="addFromPriceList({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <span class="min-w-0">
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $result->model }}</span>
                                        @if ($result->collection)
                                            <span class="text-zinc-500"> · {{ $result->collection }}</span>
                                        @endif
                                    </span>
                                    <span class="flex-shrink-0 text-zinc-500">{{ number_format($result->cost ?? 0, 2) }} $</span>
                                </button>
                            @empty
                                <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun résultat dans les listes de prix actives.') }}</div>
                            @endforelse
                        </div>
                    @endif
                </flux:field>
            @endif
        </div>
    </flux:modal>

    <livewire:customer-form />
</div>
