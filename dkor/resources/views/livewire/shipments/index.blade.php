<div class="mx-auto max-w-4xl sm:p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading level="1" size="xl">{{ __('Expéditions') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Expéditions à faire et envois des :days derniers jours', ['days' => \App\Livewire\Shipments\Index::RECENT_DAYS]) }}</flux:text>
        </div>

        @if ($this->canChooseStore())
            <flux:select wire:model.live="storeId" class="w-full sm:w-56">
                @foreach ($stores as $store)
                    <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <flux:heading size="lg" class="mb-2">{{ __('À faire') }}</flux:heading>

    @forelse ($pending as $shipment)
        <div wire:key="pending-{{ $shipment->id }}" @class([
            'mb-2 rounded-xl border bg-white p-3 shadow-sm dark:bg-zinc-900',
            'border-zinc-900 dark:border-white' => $openedId === $shipment->id,
            'border-zinc-200 dark:border-zinc-700' => $openedId !== $shipment->id,
        ])>
            <button type="button" class="w-full text-left" wire:click="open({{ $shipment->id }})">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium text-zinc-900 dark:text-white">#{{ $shipment->customer_order_id }} · {{ $shipment->order->customer->firstname }} {{ $shipment->order->customer->lastname }}</span>
                    @if ($shipment->ready_at)
                        <flux:badge size="sm" color="green" icon="check">{{ __('Tous les articles en main') }}</flux:badge>
                    @else
                        <flux:badge size="sm" color="zinc" icon="clock">{{ __('En attente des articles') }}</flux:badge>
                    @endif
                    @if ($shipment->shippingSupplier)
                        <flux:badge size="sm" color="sky">{{ $shipment->shippingSupplier->name }} · {{ $shipment->shipping_cost?->format() }}</flux:badge>
                    @endif
                </div>
                <div class="text-sm text-zinc-500">
                    {{ $shipment->address_city }} · {{ $shipment->lines->map(fn ($shipmentLine) => $shipmentLine->quantity.' × '.$shipmentLine->line->label)->join(', ') }}
                </div>
            </button>

            @if ($opened?->id === $shipment->id)
                <div class="mt-3 space-y-4 border-t border-zinc-100 pt-3 text-sm dark:border-zinc-800">
                    <div>
                        <div class="font-medium">{{ $shipment->order->customer->firstname }} {{ $shipment->order->customer->lastname }}</div>
                        <div class="text-zinc-600 dark:text-zinc-300">{{ $shipment->formattedAddress() }} · {{ $shipment->address_country }}</div>
                        <div class="text-xs text-zinc-400">{{ __('Adresse vérifiée le :date', ['date' => $shipment->address_verified_at->translatedFormat('j M Y')]) }}</div>
                    </div>

                    @if ($shipment->notes)
                        <flux:callout icon="chat-bubble-left-ellipsis" :text="$shipment->notes" />
                    @endif

                    <div class="space-y-2">
                        <flux:heading size="sm">{{ __('Articles') }}</flux:heading>
                        @foreach ($shipment->lines as $shipmentLine)
                            @php($product = $shipmentLine->line->product)
                            @php($warning = $product && $shipment->order->store ? $product->shippingWarningFor($shipment->order->store) : null)
                            <div wire:key="send-{{ $shipmentLine->id }}" class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-medium">{{ $shipmentLine->line->label }}</div>
                                    <div class="text-xs text-zinc-400">
                                        {{ __('À expédier : :planned · en main : :inHand', ['planned' => $shipmentLine->quantity, 'inHand' => $this->inHand($shipmentLine)]) }}
                                        @if ($product)
                                            · {{ __(':length × :width × :height · :weight', ['length' => $product->length ?? '?', 'width' => $product->width ?? '?', 'height' => $product->height ?? '?', 'weight' => $product->weight ?? '?']) }}
                                        @endif
                                    </div>
                                    @if ($shipmentLine->line->location)
                                        <div class="text-xs text-sky-700 dark:text-sky-400">{{ __('Emplacement : :location', ['location' => $shipmentLine->line->location->label()]) }}</div>
                                    @endif
                                    @if ($warning)
                                        <div class="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400"><flux:icon.exclamation-triangle class="h-3.5 w-3.5" /> {{ $warning }}</div>
                                    @endif
                                </div>
                                <div class="w-20">
                                    <flux:input wire:model="sendQuantities.{{ $shipmentLine->id }}" type="number" min="0" :max="$this->inHand($shipmentLine)" size="sm" />
                                </div>
                            </div>
                        @endforeach
                        <flux:text class="text-xs text-zinc-400">{{ __('Ce qui n\'est pas envoyé (pas encore en main) reste une expédition à faire, sans nouveaux frais au client.') }}</flux:text>
                    </div>

                    <form wire:submit="saveCarrier" class="flex flex-wrap items-end gap-2">
                        <flux:field class="min-w-48 flex-1">
                            <flux:label>{{ __('Transporteur') }}</flux:label>
                            <flux:select wire:model="carrierId">
                                <flux:select.option value="">{{ __('Choisir…') }}</flux:select.option>
                                @foreach ($this->getCarriers() as $carrier)
                                    <flux:select.option :value="$carrier->id">{{ $carrier->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="carrierId" />
                        </flux:field>
                        <flux:field class="w-32">
                            <flux:label>{{ __('Coût soumis') }}</flux:label>
                            <flux:input wire:model="shippingCost" inputmode="decimal" />
                            <flux:error name="shippingCost" />
                        </flux:field>
                        <flux:button type="submit">{{ __('Enregistrer') }}</flux:button>
                    </form>

                    <form wire:submit="ship" class="flex flex-wrap items-end gap-2">
                        <flux:field class="min-w-48 flex-1">
                            <flux:label>{{ __('Numéro de suivi') }}</flux:label>
                            <flux:input wire:model="trackingNumber" />
                            <flux:error name="trackingNumber" />
                        </flux:field>
                        <flux:button type="button" variant="ghost" wire:click="close">{{ __('Fermer') }}</flux:button>
                        <flux:button type="submit" variant="primary" icon="paper-airplane" :disabled="! $shipment->shipping_supplier_id">{{ __('Confirmer l\'envoi') }}</flux:button>
                    </form>
                    <flux:error name="ship" />
                </div>
            @endif
        </div>
    @empty
        <flux:text class="mb-4 text-sm text-zinc-400">{{ __('Aucune expédition à faire.') }}</flux:text>
    @endforelse

    <flux:heading size="lg" class="mb-2 mt-8">{{ __('Envoyées') }}</flux:heading>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Commande') }}</flux:table.column>
                <flux:table.column>{{ __('Transporteur') }}</flux:table.column>
                <flux:table.column>{{ __('Suivi') }}</flux:table.column>
                <flux:table.column>{{ __('Envoyée le') }}</flux:table.column>
                <flux:table.column>{{ __('Facture') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($shipped as $shipment)
                    <flux:table.row :key="'shipped-'.$shipment->id">
                        <flux:table.cell variant="strong">#{{ $shipment->customer_order_id }} · {{ $shipment->order->customer->lastname }}</flux:table.cell>
                        <flux:table.cell>{{ $shipment->shippingSupplier?->name }} · {{ $shipment->shipping_cost?->format() }}</flux:table.cell>
                        <flux:table.cell>{{ $shipment->tracking_number }}</flux:table.cell>
                        <flux:table.cell>{{ $shipment->shipped_at?->translatedFormat('j M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($shipment->supplier_invoice_id)
                                <flux:badge size="sm" color="green">{{ __('Facturée') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('À facturer') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-6 text-center text-zinc-400">{{ __('Aucun envoi récent.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
