<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Réceptions') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Réception des produits commandés aux fournisseurs') }}</flux:text>
        </div>

        @can('receptions.create')
            <flux:button variant="primary" icon="plus" :href="route('receptions.create')" wire:navigate>
                {{ __('Nouvelle réception') }}
            </flux:button>
        @endcan
    </div>

    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher par numéro, bordereau ou fournisseur…') }}" icon="magnifying-glass" />
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$receptions">
            <flux:table.columns>
                <flux:table.column>{{ __('Numéro') }}</flux:table.column>
                <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column>{{ __('Bordereau') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Unités') }}</flux:table.column>
                <flux:table.column>{{ __('Reçue le') }}</flux:table.column>
                <flux:table.column>{{ __('Par') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($receptions as $reception)
                    <flux:table.row :key="$reception->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('receptions.show', $reception)" wire:navigate>{{ $reception->number }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $reception->supplier->name }}</flux:table.cell>
                        <flux:table.cell><flux:badge :color="$reception->status->color()" size="sm">{{ $reception->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $reception->reference ?: '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $reception->lines->sum('quantity_net') }}</flux:table.cell>
                        <flux:table.cell>{{ $reception->received_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $reception->receiver ? $reception->receiver->firstname.' '.$reception->receiver->lastname : '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="inbox-arrow-down" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">
                                    {{ filled($search) ? __('Aucune réception trouvée pour cette recherche.') : __('Aucune réception. Cliquez sur « Nouvelle réception » pour commencer.') }}
                                </flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-3">
        <flux:text class="text-sm text-zinc-400">
            {{ trans_choice(':count réception|:count réceptions', $receptions->total()) }}
        </flux:text>
    </div>
</div>
