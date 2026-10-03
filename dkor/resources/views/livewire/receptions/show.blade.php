@php
    $inProgress = $reception->isInProgress();
@endphp

<div class="p-6">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:link :href="route('receptions.index')" wire:navigate class="text-sm">← {{ __('Réceptions') }}</flux:link>
            <div class="mt-1 flex items-center gap-3">
                <flux:heading level="1" size="xl">{{ $reception->number }}</flux:heading>
                <flux:badge :color="$reception->status->color()" size="sm">{{ $reception->status->label() }}</flux:badge>
            </div>
            <flux:text class="mt-1 text-zinc-500">
                <flux:link :href="route('suppliers.show', $reception->supplier)" wire:navigate>{{ $reception->supplier->name }}</flux:link>
                · {{ ($inProgress ? $reception->received_at : ($reception->completed_at ?? $reception->received_at))->format('Y-m-d H:i') }}
                @if ($reception->receiver)
                    · {{ __('par :name', ['name' => $reception->receiver->firstname.' '.$reception->receiver->lastname]) }}
                @endif
                @if (! $inProgress && $reception->reference)
                    · {{ __('Bordereau') }} {{ $reception->reference }}
                @endif
            </flux:text>
        </div>

        @if ($inProgress)
            @can('receptions.create')
                <div class="flex gap-2">
                    <flux:button variant="ghost" icon="trash" wire:click="discard" wire:confirm="{{ __('Abandonner cette réception? Rien n\'est enregistré dans l\'inventaire.') }}">{{ __('Abandonner') }}</flux:button>
                    <flux:button variant="primary" icon="check" wire:click="complete" wire:confirm="{{ __('Terminer la réception? Les quantités entreront en inventaire.') }}">{{ __('Terminer la réception') }}</flux:button>
                </div>
            @endcan
        @endif
    </div>

    @if ($inProgress)
        <flux:callout icon="information-circle" class="mb-6">
            <flux:callout.text>{{ __('Réception en cours : les articles ci-dessous entreront en inventaire seulement quand vous terminerez la réception.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Commande') }}</flux:table.column>
                <flux:table.column>{{ __('Produit') }}</flux:table.column>
                @if ($inProgress)
                    <flux:table.column align="end">{{ __('Restant à recevoir') }}</flux:table.column>
                @endif
                <flux:table.column align="end">{{ $inProgress ? __('Quantité reçue') : __('Quantité reçue') }}</flux:table.column>
                @if (! $inProgress)
                    <flux:table.column align="end">{{ __('Renversée') }}</flux:table.column>
                @endif
                @if ($inProgress)
                    <flux:table.column />
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($reception->lines as $line)
                    <flux:table.row :key="$line->id">
                        <flux:table.cell>
                            <flux:link :href="route('supplier-orders.show', $line->orderLine->order)" wire:navigate>{{ $line->orderLine->order->number }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">{{ $line->orderLine->label }}</flux:table.cell>
                        @if ($inProgress)
                            <flux:table.cell align="end">{{ $line->orderLine->quantity_outstanding }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:input wire:model="quantities.{{ $line->id }}" type="number" min="1" max="{{ $line->orderLine->quantity_outstanding }}" step="1" class="w-24" />
                                <flux:error name="quantities.{{ $line->id }}" />
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @can('receptions.create')
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeLine({{ $line->id }})" />
                                @endcan
                            </flux:table.cell>
                        @else
                            <flux:table.cell align="end">{{ $line->quantity }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $line->quantity_reversed ?: '—' }}</flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center">
                            <flux:text class="text-zinc-400">{{ __('Aucun article dans cette réception.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        @if ($inProgress)
            @can('receptions.create')
                <form wire:submit="saveQuantities" class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('N° de bordereau') }}</flux:label>
                        <flux:input wire:model="reference" type="text" />
                        <flux:error name="reference" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Notes') }}</flux:label>
                        <flux:textarea wire:model="notes" rows="3" />
                        <flux:error name="notes" />
                    </flux:field>
                    <flux:button type="submit">{{ __('Sauvegarder') }}</flux:button>
                </form>
            @endcan
        @elseif ($reception->notes)
            <flux:heading size="lg">{{ __('Notes') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-line">{{ $reception->notes }}</flux:text>
        @else
            <flux:text class="text-zinc-400">{{ __('Aucune note.') }}</flux:text>
        @endif
    </div>
</div>
