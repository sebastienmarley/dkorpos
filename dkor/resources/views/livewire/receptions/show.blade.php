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
            <flux:callout.text>{{ __('Réception en cours : les articles ci-dessous entreront en inventaire seulement quand vous terminerez la réception. Un article annoncé sur le bordereau mais absent va dans « Manquant » : le fournisseur sera avisé et un crédit lui sera demandé.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($reception->shortLines()->isNotEmpty() || $reception->overshipLines()->isNotEmpty())
        <flux:callout icon="exclamation-triangle" color="amber" class="mb-6">
            <flux:callout.text>
                {{ __('Erreur d\'expédition : :short article(s) manquant(s), :over article(s) reçu(s) en trop. Le fournisseur en a été avisé; le crédit des manquants est demandé à la facturation.', [
                    'short' => $reception->shortLines()->sum('quantity_short'),
                    'over' => $reception->overshipLines()->sum('quantity'),
                ]) }}
            </flux:callout.text>
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
                <flux:table.column align="end">{{ __('Quantité reçue') }}</flux:table.column>
                <flux:table.column align="end">{{ __('dont endommagé') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Manquant') }}</flux:table.column>
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
                        <flux:table.cell variant="strong">
                            {{ $line->orderLine->label }}
                            @if ($shipment = $line->orderLine->activeShipment())
                                <flux:badge color="amber" size="sm" icon="paper-airplane" class="ms-1">{{ __('Pour expédition — commande #:order', ['order' => $shipment->customer_order_id]) }}</flux:badge>
                            @endif
                            @if ($line->orderLine->is_overship)
                                <flux:badge color="purple" size="sm" class="ms-1">{{ __('Reçu en trop') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        @if ($inProgress)
                            <flux:table.cell align="end">{{ $line->orderLine->quantity_outstanding }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:input wire:model="quantities.{{ $line->id }}" type="number" min="0" step="1" class="w-24" />
                                <flux:error name="quantities.{{ $line->id }}" />
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($line->product_id)
                                    <flux:input wire:model="damaged.{{ $line->id }}" type="number" min="0" step="1" class="w-24" />
                                    <flux:error name="damaged.{{ $line->id }}" />
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($line->orderLine->is_overship)
                                    —
                                @else
                                    <flux:input wire:model="short.{{ $line->id }}" type="number" min="0" step="1" class="w-24" />
                                    <flux:error name="short.{{ $line->id }}" />
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @can('receptions.create')
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeLine({{ $line->id }})" />
                                @endcan
                            </flux:table.cell>
                        @else
                            <flux:table.cell align="end">{{ $line->quantity }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($line->quantity_damaged > 0)
                                    <flux:badge color="amber" size="sm">{{ $line->quantity_damaged }}</flux:badge>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($line->quantity_short > 0)
                                    <flux:badge color="red" size="sm">{{ $line->quantity_short }}</flux:badge>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $line->quantity_reversed ?: '—' }}</flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center">
                            <flux:text class="text-zinc-400">{{ __('Aucun article dans cette réception.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($inProgress)
        @can('receptions.create')
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Ajouter un article reçu en trop') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Un produit du fournisseur reçu sans être commandé (ou en plus de la commande). Il entre en inventaire à coût nul; la comptabilité ajustera son coût si le fournisseur le facture.') }}</flux:text>

                <div class="mt-4 flex flex-wrap items-start gap-3">
                    <div class="min-w-64 flex-1">
                        <flux:input wire:model.live.debounce.300ms="overshipSearch" icon="magnifying-glass" :placeholder="__('Modèle du produit…')" />
                    </div>
                    <div class="w-28">
                        <flux:input wire:model="overshipQuantity" type="number" min="1" step="1" :placeholder="__('Quantité')" />
                        <flux:error name="overshipQuantity" />
                    </div>
                    @php($receptionOrders = $this->receptionOrders())
                    @if ($receptionOrders->count() > 1)
                        <div class="w-48">
                            <flux:select wire:model="overshipOrderId">
                                @foreach ($receptionOrders as $receptionOrder)
                                    <flux:select.option :value="$receptionOrder->id">{{ $receptionOrder->number }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                </div>

                @if (mb_strlen(trim($overshipSearch)) >= 2)
                    <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @forelse ($this->getOvershipResults() as $result)
                            <button type="button" wire:key="overship-{{ $result->id }}" wire:click="addOvership({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $result->display_name }}</span>
                                <flux:icon name="plus" class="h-4 w-4 flex-shrink-0 text-zinc-400" />
                            </button>
                        @empty
                            <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun produit de ce fournisseur ne correspond.') }}</div>
                        @endforelse
                    </div>
                @endif
            </div>
        @endcan
    @endif

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
