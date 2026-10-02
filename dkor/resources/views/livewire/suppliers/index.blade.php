<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Fournisseurs') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Liste de tous les fournisseurs') }}</flux:text>
        </div>

        @can('suppliers.create')
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-supplier-create')">
                {{ __('Ajouter un fournisseur') }}
            </flux:button>
        @endcan
    </div>

    {{-- Recherche --}}
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher un fournisseur…') }}" icon="magnifying-glass" />
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Téléphone') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($suppliers->take(5) as $supplier)
                    <flux:table.row :key="$supplier->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('suppliers.show', $supplier)" wire:navigate>
                                {{ $supplier->name }}
                            </flux:link>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :color="$supplier->type->value === 'service' ? 'blue' : 'green'" size="sm">
                                {{ $supplier->type->label() }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>{{ $supplier->phone ?: '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                @if (filled($search))
                                    <flux:icon name="building-storefront" class="h-8 w-8 text-zinc-300" />
                                    <flux:text class="text-zinc-400">{{ __('Aucun fournisseur trouvé pour cette recherche.') }}</flux:text>
                                @else
                                    <flux:icon name="building-storefront" class="h-8 w-8 text-zinc-300" />
                                    <flux:text class="text-zinc-400">{{ __('Aucun fournisseur. Cliquez sur « Ajouter » pour commencer.') }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($suppliers->isNotEmpty())
        <flux:text class="mt-3 text-sm text-zinc-400">
            {{ trans_choice(':count fournisseur|:count fournisseurs', $suppliers->count()) }}
        </flux:text>
    @endif

    <livewire:supplier-form />
</div>
