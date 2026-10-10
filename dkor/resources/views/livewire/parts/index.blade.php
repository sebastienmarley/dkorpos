<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Pièces') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Pièces de remplacement commandées pour les clients') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-part-picker')">
            {{ __('Ajouter une pièce') }}
        </flux:button>
    </div>

    {{-- Filtres --}}
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher par modèle, description ou produit (SKU)…') }}" icon="magnifying-glass" />
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$parts">
            <flux:table.columns>
                <flux:table.column>{{ __('Modèle') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                <flux:table.column>{{ __('Produits') }}</flux:table.column>
                <flux:table.column>{{ __('Dernier coût') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($parts as $part)
                    <flux:table.row :key="$part->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('parts.show', $part)" wire:navigate>
                                {{ $part->model }}
                            </flux:link>
                        </flux:table.cell>

                        <flux:table.cell>{{ $part->description }}</flux:table.cell>

                        <flux:table.cell>{{ $part->supplier->name }}</flux:table.cell>

                        <flux:table.cell>
                            @if ($part->products->isEmpty())
                                <span class="text-zinc-400">—</span>
                            @else
                                {{ $part->products->pluck('model')->join(', ') }}
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ number_format($part->last_cost, 2) }} $</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="puzzle-piece" class="h-8 w-8 text-zinc-300" />
                                @if (filled($search))
                                    <flux:text class="text-zinc-400">{{ __('Aucune pièce trouvée pour ces critères.') }}</flux:text>
                                @else
                                    <flux:text class="text-zinc-400">{{ __('Aucune pièce. Cliquez sur « Ajouter une pièce » pour commencer.') }}</flux:text>
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
            {{ trans_choice(':count pièce|:count pièces', $parts->total()) }}
        </flux:text>
    </div>

    <livewire:part-picker />
</div>
