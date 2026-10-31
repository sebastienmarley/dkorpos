<div class="sm:p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading level="1" size="xl">{{ __('Commandes clients') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Commandes de vente aux clients') }}</flux:text>
        </div>

        @can('customer_orders.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Nouvelle commande') }}
            </flux:button>
        @endcan
    </div>

    {{-- Filtres --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <div class="w-full sm:w-auto sm:flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher par numéro, client ou téléphone…') }}" icon="magnifying-glass" />
        </div>

        @if ($stores->isNotEmpty())
            <div class="min-w-0 flex-1 sm:w-52 sm:flex-none">
                <flux:select wire:model.live="storeFilter">
                    <flux:select.option value="">{{ __('Tous les magasins') }}</flux:select.option>
                    @foreach ($stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        <div class="min-w-0 flex-1 sm:w-52 sm:flex-none">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="">{{ __('Tous les statuts') }}</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$orders">
            <flux:table.columns>
                <flux:table.column>{{ __('Numéro') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                @if ($stores->isNotEmpty())
                    <flux:table.column class="max-md:hidden">{{ __('Magasin') }}</flux:table.column>
                @endif
                <flux:table.column class="max-md:hidden">{{ __('Vendeur(s)') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Solde à payer') }}</flux:table.column>
                <flux:table.column class="max-md:hidden">{{ __('Créée le') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($orders as $order)
                    <flux:table.row :key="$order->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('customer-orders.show', $order)" wire:navigate>#{{ $order->id }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $order->customer->firstname }} {{ $order->customer->lastname }}</flux:table.cell>
                        @if ($stores->isNotEmpty())
                            <flux:table.cell class="max-md:hidden">{{ $order->store?->name ?? '—' }}</flux:table.cell>
                        @endif
                        <flux:table.cell class="max-md:hidden">{{ $order->salespeople->map(fn ($salesperson) => $salesperson->fullName())->join(', ') ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$order->status->color()" size="sm">{{ $order->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $order->balance_due->format() }}</flux:table.cell>
                        <flux:table.cell class="max-md:hidden">{{ $order->created_at->format('Y-m-d') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell :colspan="$stores->isNotEmpty() ? 7 : 6" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                @if ($hasSearchCriteria)
                                    <flux:icon name="shopping-cart" class="h-8 w-8 text-zinc-300" />
                                    <flux:text class="text-zinc-400">{{ __('Aucune commande trouvée pour ces critères.') }}</flux:text>
                                @else
                                    <flux:icon name="magnifying-glass" class="h-8 w-8 text-zinc-300" />
                                    <flux:text class="text-zinc-400">{{ __('Entrez un numéro, un client ou un téléphone, ou choisissez un statut pour afficher les commandes.') }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($hasSearchCriteria)
        <div class="mt-3">
            <flux:text class="text-sm text-zinc-400">
                {{ trans_choice(':count commande|:count commandes', $orders->total()) }}
            </flux:text>
        </div>
    @endif

    {{-- Création : choix du client --}}
    @can('customer_orders.create')
        <flux:modal wire:model="showCreate" class="w-full max-w-lg">
            <flux:heading class="mb-1">{{ __('Nouvelle commande client') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Choisissez ou créez le client de la commande.') }}</flux:text>

            @if ($stores->isNotEmpty())
                <flux:select wire:model="createStoreId" :label="__('Magasin')" class="mt-6">
                    @foreach ($stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="mt-6">
                <x-customer-search :results="$customerResults" :search="$customerSearch" select="create" />
            </div>
        </flux:modal>

        <livewire:customer-form />
    @endcan
</div>
