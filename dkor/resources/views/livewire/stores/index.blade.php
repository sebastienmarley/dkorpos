<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Magasins') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Liste de tous les emplacements, physiques et virtuels') }}</flux:text>
        </div>

        @can('stores.create')
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-store-create')">
                {{ __('Ajouter un magasin') }}
            </flux:button>
        @endcan
    </div>

    {{-- Recherche --}}
    <div class="mb-4 flex items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher un magasin…') }}" icon="magnifying-glass" class="flex-1" />
        <flux:checkbox wire:model.live="showInactive" :label="__('Afficher les inactifs')" />
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$stores">
            <flux:table.columns>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Téléphone') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($stores as $store)
                    <flux:table.row :key="$store->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('stores.show', $store)" wire:navigate>
                                {{ $store->name }}
                            </flux:link>
                            @if (! $store->is_active)
                                <flux:badge color="zinc" size="sm" class="ms-2">{{ __('Inactif') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :color="$store->type->color()" size="sm">
                                {{ $store->type->label() }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>{{ $store->phone ?: '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="building-storefront" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">
                                    {{ filled($search) ? __('Aucun magasin trouvé pour cette recherche.') : __('Aucun magasin. Cliquez sur « Ajouter » pour commencer.') }}
                                </flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($stores->total() > 0)
        <flux:text class="mt-3 text-sm text-zinc-400">
            {{ trans_choice(':count magasin|:count magasins', $stores->total()) }}
        </flux:text>
    @endif

    <livewire:store-form />
</div>
