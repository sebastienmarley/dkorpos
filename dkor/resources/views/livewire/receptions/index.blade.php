<div class="sm:p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <flux:heading level="1" size="xl">{{ __('Réceptions') }}</flux:heading>
            <flux:tooltip :content="__('Réception des produits commandés aux fournisseurs')">
                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
            </flux:tooltip>
        </div>

        @can('receptions.create')
            <flux:button variant="primary" icon="plus" :href="route('receptions.create')" wire:navigate>
                {{ __('Nouvelle réception') }}
            </flux:button>
        @endcan
    </div>

    {{-- Filtres --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <div class="w-full sm:w-auto sm:flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('# Commande ou bordereau…') }}" icon="magnifying-glass" />
        </div>

        <div class="min-w-0 flex-1 sm:w-52 sm:flex-none">
            <flux:select wire:model.live="supplierFilter">
                <flux:select.option value="">{{ __('Tous les fournisseurs') }}</flux:select.option>
                @foreach ($suppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="min-w-0 flex-1 sm:w-52 sm:flex-none">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="">{{ __('Tous les statuts') }}</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Résultats : rien avant une recherche, la table seulement s'il y a des réceptions --}}
    @if ($receptions->isNotEmpty())
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
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($receptions as $reception)
                        <x-table.row-link :key="$reception->id" :href="route('receptions.show', $reception)">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('receptions.show', $reception)" wire:navigate>{{ $reception->number }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $reception->supplier->name }}</flux:table.cell>
                            <flux:table.cell><flux:badge :color="$reception->status->color()" size="sm">{{ $reception->status->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $reception->reference ?: '—' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $reception->lines->sum('quantity_net') }}</flux:table.cell>
                            <flux:table.cell>{{ $reception->received_at->format('Y-m-d H:i') }}</flux:table.cell>
                            <flux:table.cell>{{ $reception->receiver ? $reception->receiver->firstname.' '.$reception->receiver->lastname : '—' }}</flux:table.cell>
                        </x-table.row-link>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <flux:text class="mt-3 text-sm text-zinc-400">
            {{ trans_choice(':count réception|:count réceptions', $receptions->total()) }}
        </flux:text>
    @elseif ($hasSearchCriteria)
        <div class="flex flex-col items-center gap-2 py-12 text-center">
            <flux:icon name="inbox-arrow-down" class="h-8 w-8 text-zinc-300" />
            <flux:text class="text-zinc-400">{{ __('Aucune réception trouvée pour ces critères.') }}</flux:text>
        </div>
    @endif
</div>
