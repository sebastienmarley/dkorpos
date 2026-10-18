<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Prises d\'inventaire') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Comptage annuel par type de compte : stock, stock client, défectueux et démonstrateurs.') }}</flux:text>
        </div>

        @can('inventory.adjust')
            @if ($inProgress)
                <flux:button variant="primary" icon="arrow-right" :href="route('inventory.counts.show', $inProgress)" wire:navigate>{{ __('Reprendre la prise en cours') }}</flux:button>
            @else
                <flux:button variant="primary" icon="clipboard-document-check" wire:click="openStart">{{ __('Démarrer une prise') }}</flux:button>
            @endif
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$counts">
            <flux:table.columns>
                <flux:table.column>{{ __('Date de comptage') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Lignes') }}</flux:table.column>
                <flux:table.column>{{ __('Démarrée par') }}</flux:table.column>
                <flux:table.column>{{ __('Finalisée') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($counts as $count)
                    <flux:table.row :key="$count->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('inventory.counts.show', $count)" wire:navigate>{{ $count->counted_on->format('Y-m-d') }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$count->status->color()" size="sm">{{ $count->status->label() }}</flux:badge>
                            @if ($count->isInProgress() && $count->stock_frozen_at)
                                <flux:badge color="sky" size="sm">{{ __('Stock gelé') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $count->lines_count }}</flux:table.cell>
                        <flux:table.cell>{{ $count->starter ? $count->starter->firstname.' '.$count->starter->lastname : '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($count->finalized_at)
                                {{ $count->finalized_at->format('Y-m-d H:i') }}
                                @if ($count->finalizer)
                                    · {{ $count->finalizer->firstname }} {{ $count->finalizer->lastname }}
                                @endif
                            @else
                                —
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="clipboard-document-check" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucune prise d\'inventaire.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showStart" class="w-full max-w-md">
        <form wire:submit="start" class="space-y-4">
            <flux:heading>{{ __('Démarrer une prise d\'inventaire') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Les articles « Perdu » non retrouvés reviennent « En stock ». Démo, défectueux et stock client se comptent pendant la journée; le stock, après la fermeture, une fois gelé. Les écritures porteront la date de comptage.') }}</flux:text>

            <flux:input wire:model="countedOn" type="date" :label="__('Date de comptage')" required />

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showStart', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Démarrer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
