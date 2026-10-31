<div class="mx-auto max-w-2xl sm:p-6">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Mes livraisons') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ ucfirst(\Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F Y')) }}</flux:text>
        </div>

        <flux:button.group class="shrink-0">
            <flux:button icon="chevron-left" wire:click="previousDay" :label="__('Journée précédente')" />
            <flux:button wire:click="goToToday">{{ __('Aujourd\'hui') }}</flux:button>
            <flux:button icon="chevron-right" wire:click="nextDay" :label="__('Journée suivante')" />
        </flux:button.group>
    </div>

    @if ($this->canChooseStore())
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
                    <div class="flex flex-wrap items-start gap-3">
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
                                                @if ($deliveryLine->line->location)
                                                    <div class="text-xs text-sky-700 dark:text-sky-400">{{ __('Emplacement : :location', ['location' => $deliveryLine->line->location->label()]) }}</div>
                                                @endif
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
                                @if ($toCollect->isPositive())
                                    <form wire:submit="collectPayment" class="space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950">
                                        <flux:heading size="sm">{{ __('Paiement à percevoir : :amount', ['amount' => $toCollect->format()]) }}</flux:heading>
                                        <flux:text class="text-xs">{{ __('La confirmation de la livraison est bloquée tant que le paiement n\'est pas enregistré.') }}</flux:text>
                                        @if ($this->getDeliveryPaymentMethods()->isEmpty())
                                            <flux:text class="text-xs text-red-600 dark:text-red-400">{{ __('Aucun mode de paiement utilisable à la livraison : un gestionnaire doit en marquer un dans Comptabilité > Modes de paiement.') }}</flux:text>
                                        @else
                                            <div class="flex flex-wrap items-start gap-2">
                                                <flux:select wire:model="payment.method_id" class="min-w-40 flex-1">
                                                    @foreach ($this->getDeliveryPaymentMethods() as $method)
                                                        <flux:select.option :value="$method->id">{{ $method->name }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                                <div class="w-28">
                                                    <flux:input wire:model="payment.amount" inputmode="decimal" />
                                                </div>
                                                <flux:button type="submit" variant="primary" icon="banknotes">{{ __('Enregistrer le paiement') }}</flux:button>
                                            </div>
                                            <flux:error name="payment.method_id" />
                                            <flux:error name="payment.amount" />
                                            <flux:error name="payment" />
                                        @endif
                                    </form>
                                @endif

                                @if ($completionStep === '')
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <flux:button type="button" variant="ghost" wire:click="close">{{ __('Fermer') }}</flux:button>
                                        <flux:button
                                            type="button"
                                            icon="user-minus"
                                            wire:click="markUndelivered('customer_absent')"
                                            wire:confirm="{{ __('Le client est absent ? Les articles reviendront au magasin et la livraison sera non livrée.') }}"
                                        >
                                            {{ __('Client absent') }}
                                        </flux:button>
                                        <flux:button type="button" icon="x-circle" wire:click="$set('completionStep', 'refused')">{{ __('Le client refuse tout') }}</flux:button>
                                        <flux:button type="button" variant="primary" icon="check" wire:click="startConfirmation" :disabled="$toCollect->isPositive()">{{ __('Confirmer la livraison') }}</flux:button>
                                    </div>
                                    <flux:error name="refused" />
                                @elseif ($completionStep === 'checklist')
                                    @php($allChecked = collect($acceptedChecks)->every(fn ($checked) => (bool) $checked))
                                    <div class="space-y-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                        <flux:heading size="sm">{{ __('Articles reçus par le client') }}</flux:heading>
                                        <flux:checkbox :checked="$allChecked" wire:click="toggleAll({{ $allChecked ? 'false' : 'true' }})" :label="__('Tout accepter')" />

                                        @foreach ($delivery->lines as $deliveryLine)
                                            @php($isChecked = (bool) ($acceptedChecks[$deliveryLine->id] ?? false))
                                            <div wire:key="check-{{ $deliveryLine->id }}" class="space-y-1 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                                                <div class="flex items-center justify-between gap-3">
                                                    <flux:checkbox wire:model.live="acceptedChecks.{{ $deliveryLine->id }}" :label="$deliveryLine->quantity.' × '.$deliveryLine->line->label" />
                                                    @if ($isChecked && $deliveryLine->quantity > 1)
                                                        <div class="w-20">
                                                            <flux:input wire:model.live="acceptedQuantities.{{ $deliveryLine->id }}" type="number" min="1" :max="$deliveryLine->quantity" size="sm" />
                                                        </div>
                                                    @endif
                                                </div>
                                                @if (! $isChecked || (int) ($acceptedQuantities[$deliveryLine->id] ?? $deliveryLine->quantity) < $deliveryLine->quantity)
                                                    <flux:input wire:model="refusalReasons.{{ $deliveryLine->id }}" size="sm" :placeholder="__('Raison du refus (endommagé, mauvais article, refusé par le client…)')" />
                                                    <flux:error name="refusalReasons.{{ $deliveryLine->id }}" />
                                                    @include('livewire.deliveries.partials.return-location', ['deliveryLine' => $deliveryLine])
                                                @endif
                                            </div>
                                        @endforeach

                                        <flux:error name="checklist" />
                                        <div class="flex justify-end gap-2">
                                            <flux:button type="button" variant="ghost" wire:click="$set('completionStep', '')">{{ __('Retour') }}</flux:button>
                                            <flux:button type="button" variant="primary" wire:click="confirmReceipt">{{ __('J\'ai reçu ces articles, en bon état') }}</flux:button>
                                        </div>
                                    </div>
                                @elseif ($completionStep === 'signature')
                                    <div class="space-y-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                                        x-data="{
                                            drawing: false,
                                            hasInk: false,
                                            context: null,
                                            init() {
                                                const canvas = this.$refs.canvas;
                                                const ratio = window.devicePixelRatio || 1;
                                                canvas.width = canvas.offsetWidth * ratio;
                                                canvas.height = canvas.offsetHeight * ratio;
                                                this.context = canvas.getContext('2d');
                                                this.context.scale(ratio, ratio);
                                                this.context.lineWidth = 2;
                                                this.context.lineCap = 'round';
                                                this.context.lineJoin = 'round';
                                                this.clear();
                                            },
                                            point(event) {
                                                const box = this.$refs.canvas.getBoundingClientRect();
                                                return { x: event.clientX - box.left, y: event.clientY - box.top };
                                            },
                                            start(event) {
                                                this.drawing = true;
                                                const { x, y } = this.point(event);
                                                this.context.beginPath();
                                                this.context.moveTo(x, y);
                                            },
                                            draw(event) {
                                                if (! this.drawing) return;
                                                const { x, y } = this.point(event);
                                                this.context.lineTo(x, y);
                                                this.context.stroke();
                                                this.hasInk = true;
                                            },
                                            stop() { this.drawing = false; },
                                            clear() {
                                                const canvas = this.$refs.canvas;
                                                this.context.fillStyle = '#ffffff';
                                                this.context.fillRect(0, 0, canvas.width, canvas.height);
                                                this.context.strokeStyle = '#111827';
                                                this.hasInk = false;
                                            },
                                            finish() {
                                                $wire.complete(this.hasInk ? this.$refs.canvas.toDataURL('image/png') : '');
                                            },
                                        }"
                                    >
                                        <flux:heading size="sm">{{ __('Signature du client') }}</flux:heading>
                                        <flux:field>
                                            <flux:label>{{ __('Nom du signataire') }}</flux:label>
                                            <flux:input wire:model="signerName" />
                                            <flux:error name="signerName" />
                                        </flux:field>
                                        <div wire:ignore>
                                            <canvas
                                                x-ref="canvas"
                                                class="h-40 w-full rounded border border-zinc-300 bg-white"
                                                style="touch-action: none"
                                                x-on:pointerdown.prevent="start($event)"
                                                x-on:pointermove.prevent="draw($event)"
                                                x-on:pointerup="stop()"
                                                x-on:pointerleave="stop()"
                                            ></canvas>
                                        </div>
                                        <flux:error name="signature" />
                                        <div class="flex justify-end gap-2">
                                            <flux:button type="button" variant="ghost" wire:click="$set('completionStep', 'checklist')">{{ __('Retour') }}</flux:button>
                                            <flux:button type="button" icon="arrow-path" x-on:click="clear()">{{ __('Effacer') }}</flux:button>
                                            <flux:button type="button" variant="primary" icon="check" x-on:click="finish()">{{ __('Terminé') }}</flux:button>
                                        </div>
                                    </div>
                                @elseif ($completionStep === 'refused')
                                    <div class="space-y-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                        <flux:heading size="sm">{{ __('Le client refuse tous les articles') }}</flux:heading>
                                        @foreach ($delivery->lines as $deliveryLine)
                                            <div wire:key="refuse-{{ $deliveryLine->id }}">
                                                <flux:input wire:model="refusalReasons.{{ $deliveryLine->id }}" size="sm" :label="$deliveryLine->quantity.' × '.$deliveryLine->line->label" :placeholder="__('Raison du refus')" />
                                                @include('livewire.deliveries.partials.return-location', ['deliveryLine' => $deliveryLine])
                                            </div>
                                        @endforeach
                                        <flux:error name="refused" />
                                        <div class="flex justify-end gap-2">
                                            <flux:button type="button" variant="ghost" wire:click="$set('completionStep', '')">{{ __('Retour') }}</flux:button>
                                            <flux:button type="button" variant="danger" wire:click="markUndelivered('all_refused')">{{ __('Confirmer : tout refusé') }}</flux:button>
                                        </div>
                                    </div>
                                @endif
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
