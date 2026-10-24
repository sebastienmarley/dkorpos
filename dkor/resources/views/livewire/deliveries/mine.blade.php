<div class="mx-auto max-w-2xl p-4 sm:p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading level="1" size="xl">{{ __('Mes livraisons') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ ucfirst(\Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F Y')) }}</flux:text>
        </div>

        <flux:button.group>
            <flux:button icon="chevron-left" wire:click="previousDay" :label="__('Journée précédente')" />
            <flux:button wire:click="goToToday">{{ __('Aujourd\'hui') }}</flux:button>
            <flux:button icon="chevron-right" wire:click="nextDay" :label="__('Journée suivante')" />
        </flux:button.group>
    </div>

    @if (! auth()->user()->store_id)
        <flux:select wire:model.live="storeId" class="mb-4">
            @foreach ($stores as $store)
                <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
            @endforeach
        </flux:select>
    @endif

    @foreach (\App\Enums\DeliveryPeriod::cases() as $period)
        <div class="mb-6" wire:key="period-{{ $period->value }}">
            <flux:heading size="lg" class="mb-2">{{ $period->label() }}</flux:heading>

            @forelse ($byPeriod[$period->value] as $index => $delivery)
                @php($missing = $this->missingItems($delivery))
                @php($toCollect = $this->amountToCollect($delivery))
                <div wire:key="delivery-{{ $delivery->id }}" @class([
                    'mb-2 rounded-xl border bg-white p-3 shadow-sm dark:bg-zinc-900',
                    'border-zinc-900 dark:border-white' => $openedId === $delivery->id,
                    'border-zinc-200 dark:border-zinc-700' => $openedId !== $delivery->id,
                    'opacity-60' => ! $delivery->status->isActive(),
                ])>
                    <div class="flex items-start gap-3">
                        <div class="flex flex-col items-center">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold dark:bg-zinc-800">{{ $index + 1 }}</span>
                            @if ($delivery->status->isActive())
                                <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="move({{ $delivery->id }}, -1)" :label="__('Plus tôt')" />
                                <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="move({{ $delivery->id }}, 1)" :label="__('Plus tard')" />
                            @endif
                        </div>

                        <button type="button" class="min-w-0 flex-1 text-left" wire:click="open({{ $delivery->id }})">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $delivery->order->customer->firstname }} {{ $delivery->order->customer->lastname }}</span>
                                <flux:badge size="sm" :color="$delivery->status->color()">{{ $delivery->statusLabel() }}</flux:badge>
                                @if ($missing !== [])
                                    <flux:badge size="sm" color="amber" icon="clock">{{ __('En attente d\'articles') }}</flux:badge>
                                @endif
                                @if ($toCollect->isPositive())
                                    <flux:badge size="sm" color="amber" icon="banknotes">{{ __('À percevoir : :amount', ['amount' => $toCollect->format()]) }}</flux:badge>
                                @endif
                            </div>
                            <div class="text-sm text-zinc-500">
                                {{ $delivery->address_city }} · {{ trans_choice(':count article|:count articles', $delivery->lines->sum('quantity')) }} · {{ __('Commande #:id', ['id' => $delivery->customer_order_id]) }}
                            </div>
                            @if ($missing !== [])
                                <div class="text-xs text-amber-600 dark:text-amber-400">{{ __('Encore en commande : :items', ['items' => implode(', ', $missing)]) }}</div>
                            @endif
                        </button>
                    </div>

                    @if ($opened?->id === $delivery->id)
                        <div class="mt-3 space-y-3 border-t border-zinc-100 pt-3 text-sm dark:border-zinc-800">
                            <a href="{{ $this->directionsUrl($delivery) }}" target="_blank" rel="noopener" class="flex items-center gap-2 font-medium text-sky-700 underline dark:text-sky-400">
                                <flux:icon.map-pin class="h-4 w-4" /> {{ $delivery->formattedAddress() }}
                            </a>

                            <div class="flex flex-wrap gap-3">
                                @foreach (array_filter([$delivery->order->customer->phone, $delivery->order->customer->cellphone]) as $phone)
                                    <a href="tel:{{ preg_replace('/\D/', '', $phone) }}" class="flex items-center gap-1 text-sky-700 underline dark:text-sky-400">
                                        <flux:icon.phone class="h-4 w-4" /> {{ $phone }}
                                    </a>
                                @endforeach
                            </div>

                            @if ($delivery->notes)
                                <flux:callout icon="chat-bubble-left-ellipsis" :text="$delivery->notes" />
                            @endif

                            @if ($delivery->status === \App\Enums\DeliveryStatus::Scheduled)
                                <form wire:submit="depart" class="space-y-2">
                                    <flux:heading size="sm">{{ __('Articles chargés dans le camion') }}</flux:heading>
                                    @foreach ($delivery->lines as $deliveryLine)
                                        <div wire:key="load-{{ $deliveryLine->id }}" class="flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="font-medium">{{ $deliveryLine->line->label }}</div>
                                                <div class="text-xs text-zinc-400">
                                                    {{ __('Prévu : :planned · en main : :loadable', ['planned' => $deliveryLine->quantity, 'loadable' => $this->loadable($deliveryLine)]) }}
                                                </div>
                                            </div>
                                            <div class="w-20">
                                                <flux:input wire:model="loadQuantities.{{ $deliveryLine->id }}" type="number" min="0" :max="$this->loadable($deliveryLine)" size="sm" />
                                            </div>
                                        </div>
                                    @endforeach
                                    <flux:text class="text-xs text-zinc-400">{{ __('Un article non chargé est retiré de la livraison et reste réservé au client.') }}</flux:text>
                                    <flux:error name="load" />
                                    <div class="flex justify-end gap-2">
                                        <flux:button type="button" variant="ghost" wire:click="close">{{ __('Fermer') }}</flux:button>
                                        <flux:button type="submit" variant="primary" icon="truck">{{ __('Confirmer le départ') }}</flux:button>
                                    </div>
                                </form>
                            @elseif ($delivery->status === \App\Enums\DeliveryStatus::InTransit)
                                <form wire:submit="complete" class="space-y-2">
                                    <flux:heading size="sm">{{ __('Articles acceptés par le client') }}</flux:heading>
                                    @foreach ($delivery->lines as $deliveryLine)
                                        <div wire:key="accept-{{ $deliveryLine->id }}" class="space-y-1">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="font-medium">{{ $deliveryLine->line->label }}</div>
                                                    <div class="text-xs text-zinc-400">{{ __('Chargé : :count', ['count' => $deliveryLine->quantity]) }}</div>
                                                </div>
                                                <div class="w-20">
                                                    <flux:input wire:model.live="acceptedQuantities.{{ $deliveryLine->id }}" type="number" min="0" :max="$deliveryLine->quantity" size="sm" />
                                                </div>
                                            </div>
                                            @if ((int) ($acceptedQuantities[$deliveryLine->id] ?? 0) < $deliveryLine->quantity)
                                                <flux:input wire:model="refusalReasons.{{ $deliveryLine->id }}" size="sm" :placeholder="__('Raison du refus (endommagé, mauvais article…)')" />
                                            @endif
                                        </div>
                                    @endforeach
                                    <flux:error name="complete" />
                                    <div class="flex justify-end gap-2">
                                        <flux:button type="button" variant="ghost" wire:click="close">{{ __('Fermer') }}</flux:button>
                                        <flux:button type="submit" variant="primary" icon="check">{{ __('Remettre au client') }}</flux:button>
                                    </div>
                                </form>
                            @else
                                <ul class="space-y-1">
                                    @foreach ($delivery->lines as $deliveryLine)
                                        <li wire:key="done-{{ $deliveryLine->id }}">
                                            {{ ($deliveryLine->accepted_quantity ?? $deliveryLine->quantity).' × '.$deliveryLine->line->label }}
                                            @if ($deliveryLine->refusal_reason)
                                                <span class="text-xs text-red-600 dark:text-red-400">{{ __(':count refusé(s) : :reason', ['count' => $deliveryLine->quantity - (int) $deliveryLine->accepted_quantity, 'reason' => $deliveryLine->refusal_reason]) }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <flux:text class="text-sm text-zinc-400">{{ __('Aucune livraison.') }}</flux:text>
            @endforelse
        </div>
    @endforeach
</div>
