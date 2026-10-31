<div>
    @if ($deliveries->isNotEmpty())
        <div class="mb-6">
            <flux:heading size="sm" class="mb-2">{{ __('Livraisons') }}</flux:heading>

            <div class="space-y-2">
                @foreach ($deliveries as $delivery)
                    <div wire:key="delivery-{{ $delivery->id }}" @class([
                        'flex flex-wrap items-start justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900',
                        'opacity-60' => ! $delivery->status->isActive(),
                    ])>
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:icon.truck class="h-4 w-4 text-zinc-400" />
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $delivery->title() }}</span>
                                <flux:badge size="sm" :color="$delivery->status->color()">{{ $delivery->statusLabel() }}</flux:badge>
                                @if ($delivery->method === \App\Enums\DeliveryMethod::Shipping && $delivery->status->isActive())
                                    @if ($delivery->ready_at)
                                        <flux:badge size="sm" color="green" icon="check">{{ __('Tous les articles en main') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc" icon="clock">{{ __('En attente des articles') }}</flux:badge>
                                    @endif
                                @endif
                                @if ($delivery->pay_on_delivery)
                                    <flux:badge size="sm" color="amber" icon="banknotes">{{ __('Paiement à la livraison') }}</flux:badge>
                                @endif
                            </div>
                            <div class="text-zinc-600 dark:text-zinc-300">{{ $delivery->formattedAddress() }}</div>
                            <div class="text-zinc-500">
                                {{ $delivery->lines->map(fn ($deliveryLine) => $deliveryLine->quantity.' × '.$deliveryLine->line->label)->join(', ') }}
                            </div>
                            @if ($delivery->notes)
                                <div class="text-xs text-zinc-400">{{ $delivery->notes }}</div>
                            @endif
                            @if ($delivery->cancellation_reason)
                                <div class="text-xs text-zinc-400">{{ $delivery->cancellation_reason }}</div>
                            @endif
                            @if ($delivery->shipped_at)
                                <div class="text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ __('Expédiée le :date par :carrier — suivi : :tracking', ['date' => $delivery->shipped_at->translatedFormat('j M Y'), 'carrier' => $delivery->shippingSupplier?->name ?? '—', 'tracking' => $delivery->tracking_number]) }}
                                </div>
                            @elseif ($delivery->shippingSupplier)
                                <div class="text-xs text-zinc-500">{{ __('Transporteur choisi : :carrier', ['carrier' => $delivery->shippingSupplier->name]) }}</div>
                            @endif
                            @if ($delivery->undelivered_reason)
                                <div class="text-xs text-orange-600 dark:text-orange-400">
                                    {{ __('Non livrée le :date : :reason. Les articles sont revenus en stock client.', ['date' => $delivery->undelivered_at?->translatedFormat('j M Y à H:i'), 'reason' => $delivery->undelivered_reason->label()]) }}
                                </div>
                            @endif
                            @if (in_array($delivery->status, [\App\Enums\DeliveryStatus::Delivered, \App\Enums\DeliveryStatus::Undelivered], true))
                                <ul class="text-xs text-zinc-500">
                                    @foreach ($delivery->lines as $deliveryLine)
                                        <li wire:key="outcome-{{ $deliveryLine->id }}">
                                            {{ __(':accepted / :quantity × :label accepté(s)', ['accepted' => (int) $deliveryLine->accepted_quantity, 'quantity' => $deliveryLine->quantity, 'label' => $deliveryLine->line->label]) }}
                                            @if ($deliveryLine->refusal_reason)
                                                <span class="text-red-600 dark:text-red-400">— {{ $deliveryLine->refusal_reason }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($delivery->signed_at)
                                <div class="flex items-end gap-3 pt-1">
                                    @if ($delivery->signature_path)
                                        <img src="{{ route('deliveries.signature', $delivery) }}" alt="{{ __('Signature de :name', ['name' => $delivery->signed_by_name]) }}" class="h-16 rounded border border-zinc-200 bg-white dark:border-zinc-700" />
                                    @endif
                                    <div class="text-xs text-zinc-500">
                                        {{ __('Signé par :name le :date', ['name' => $delivery->signed_by_name, 'date' => $delivery->signed_at->translatedFormat('j M Y à H:i')]) }}
                                        @unless ($delivery->signature_path)
                                            <div class="text-zinc-400">{{ __('Image supprimée après la durée de conservation.') }}</div>
                                        @endunless
                                    </div>
                                </div>
                            @endif
                        </div>

                        @can('customer_orders.edit')
                            @if ($delivery->status === \App\Enums\DeliveryStatus::Scheduled)
                                <div class="flex flex-wrap gap-2">
                                    @if ($delivery->method === \App\Enums\DeliveryMethod::Internal)
                                        <flux:button size="sm" icon="calendar" wire:click="openReschedule({{ $delivery->id }})">{{ __('Reporter') }}</flux:button>
                                    @endif
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="x-mark"
                                        wire:click="cancelDelivery({{ $delivery->id }})"
                                        wire:confirm="{{ $delivery->method === \App\Enums\DeliveryMethod::Shipping ? __('Annuler l\'expédition ? L\'entrepôt sera avisé.') : __('Annuler la livraison du :slot ? Les livreurs seront avisés.', ['slot' => $delivery->slotLabel()]) }}"
                                    >
                                        {{ __('Annuler') }}
                                    </flux:button>
                                </div>
                            @endif
                        @endcan
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @can('customer_orders.edit')
        <flux:modal wire:model="showModal" class="w-full max-w-2xl">
            <flux:heading class="mb-1">{{ match (true) { (bool) $rescheduling => __('Reporter la livraison'), $this->isShipping() => __('Expédition'), default => __('Livraison') } }}</flux:heading>

            @if ($step === 'address')
                <flux:text class="text-zinc-500">{{ __('Vérifiez l\'adresse de livraison avec le client.') }}</flux:text>

                <div class="mt-6 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="font-medium text-zinc-900 dark:text-white">{{ $customer->firstname }} {{ $customer->lastname }}</div>
                    <div class="text-zinc-600 dark:text-zinc-300">
                        {{ trim(($customer->address_civic ?? '').($customer->address_apartment ? '-'.$customer->address_apartment : '').' '.($customer->address_street ?? '')) ?: '—' }}
                    </div>
                    <div class="text-zinc-600 dark:text-zinc-300">
                        {{ trim(($customer->address_city ?? '').' '.($customer->address_province ?? '').' '.($customer->address_postal_code ?? '')) ?: '—' }}
                        @if ($customer->address_country)
                            · {{ $customer->address_country }}
                        @endif
                    </div>
                    @if ($customer->phone || $customer->cellphone)
                        <div class="mt-1 text-zinc-500">{{ collect([$customer->phone, $customer->cellphone])->filter()->join(' · ') }}</div>
                    @endif
                </div>

                @unless ($customer->hasCompleteAddress())
                    <flux:callout icon="exclamation-triangle" color="amber" class="mt-4" :text="__('L\'adresse est incomplète (numéro civique, rue, ville, province, pays et code postal) : corrigez la fiche du client.')" />
                @endunless

                <flux:error name="address" />

                <div class="mt-6 flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                    @can('customers.edit')
                        <flux:button type="button" icon="pencil" wire:click="editAddress">{{ __('Non vérifiée') }}</flux:button>
                    @endcan
                    <flux:button type="button" variant="primary" icon="check" wire:click="confirmAddress" :disabled="! $customer->hasCompleteAddress()">{{ __('Adresse confirmée') }}</flux:button>
                </div>
            @elseif ($step === 'items')
                <flux:text class="text-zinc-500">{{ __('Articles à livrer. Ils peuvent être encore en commande : la livraison attend leur réception.') }}</flux:text>

                <form wire:submit="continueFromItems" class="mt-6 space-y-3">
                    @foreach ($this->getDeliverableLines() as $line)
                        <div wire:key="delivery-line-{{ $line->id }}" class="flex items-start justify-between gap-3">
                            <div class="min-w-0 text-sm">
                                <div class="font-medium text-zinc-900 dark:text-white">
                                    {{ $line->label }}
                                    @if ($line->isPart())
                                        <flux:badge size="sm" color="amber" class="ms-1">{{ __('Pièce') }}</flux:badge>
                                    @else
                                        <span class="font-normal text-zinc-500">#{{ $line->product_id }}</span>
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-400">
                                    {{ __('À livrer au plus : :count', ['count' => $available[$line->id] ?? 0]) }} · {{ $line->unit_price->format() }}
                                    @if ($line->quantity_on_order > 0)
                                        · <span class="text-amber-600 dark:text-amber-400">{{ __(':count encore en commande', ['count' => $line->quantity_on_order]) }}</span>
                                    @endif
                                </div>
                                @if ($warning = ($shippingWarnings[$line->id] ?? null))
                                    <div class="mt-1 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                        <flux:icon.exclamation-triangle class="h-3.5 w-3.5" /> {{ $warning }}
                                    </div>
                                @endif
                            </div>
                            <div class="w-24">
                                <flux:input wire:model="quantities.{{ $line->id }}" type="number" min="0" :max="$available[$line->id] ?? 0" size="sm" />
                                <flux:error name="quantities.{{ $line->id }}" />
                            </div>
                        </div>
                    @endforeach

                    <flux:error name="quantities" />

                    @if ($this->isShipping())
                        @if ($order->shippingFeeLines()->isEmpty())
                            <flux:field>
                                <flux:checkbox wire:model="addShippingFee" :label="__('Ajouter les frais d\'expédition (:price)', ['price' => \App\Models\Service::shippingFee()->selling_price?->format() ?? '0,00 $'])" />
                                <flux:description>{{ __('Ajoutés à la commande en continuant; ils doivent être payés avec les articles expédiés.') }}</flux:description>
                                <flux:error name="addShippingFee" />
                            </flux:field>
                        @else
                            <flux:text class="text-sm text-zinc-500">{{ __('Les frais d\'expédition sont déjà sur la commande.') }}</flux:text>
                        @endif
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="$set('step', 'address')">{{ __('Retour') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Continuer') }}</flux:button>
                    </div>
                </form>
            @elseif ($step === 'payment')
                <flux:callout icon="banknotes" color="amber" class="mt-4">
                    @if ($this->isShipping())
                        <flux:callout.heading>{{ __('Réglez la facture avant l\'expédition') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Un article expédié doit être payé à 100 %, comme les frais d\'expédition, et le dépôt couvert sur le reste : :amount $ à payer.', ['amount' => $this->requiredAmount()->toDecimal()]) }}
                        </flux:callout.text>
                    @else
                        <flux:callout.heading>{{ __('Réglez la facture avant la livraison') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Les articles livrés doivent être payés à 100 % et le dépôt couvert sur le reste : :amount $ à payer.', ['amount' => $this->requiredAmount()->toDecimal()]) }}
                        </flux:callout.text>
                    @endif
                </flux:callout>

                <flux:error name="payment" />

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="backToItems">{{ __('Retour') }}</flux:button>
                    @if (! $this->isShipping())
                        @can('customer_orders.deliver_unpaid')
                            <flux:button type="button" icon="truck" wire:click="choosePayOnDelivery">{{ __('Paiement à la livraison') }}</flux:button>
                        @endcan
                    @endif
                    <flux:button type="button" variant="primary" icon="banknotes" wire:click="settleInvoice">{{ __('Encaisser un paiement') }}</flux:button>
                </div>
            @elseif ($step === 'confirm')
                <flux:text class="text-zinc-500">{{ __('L\'expédition sera à faire dès que tous les articles seront en main. Le transporteur se choisit dans le menu d\'expédition.') }}</flux:text>

                <form wire:submit="requestShipment" class="mt-6 space-y-4">
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="font-medium text-zinc-900 dark:text-white">{{ $customer->firstname }} {{ $customer->lastname }}</div>
                        <div class="text-zinc-600 dark:text-zinc-300">
                            {{ trim(($customer->address_civic ?? '').($customer->address_apartment ? '-'.$customer->address_apartment : '').' '.($customer->address_street ?? '')) }},
                            {{ trim(($customer->address_city ?? '').' '.($customer->address_province ?? '').' '.($customer->address_postal_code ?? '')) }}
                        </div>
                        <div class="mt-2 text-zinc-500">
                            {{ $this->getDeliverableLines()->filter(fn ($line) => (int) ($quantities[$line->id] ?? 0) > 0)->map(fn ($line) => (int) $quantities[$line->id].' × '.$line->label)->join(', ') }}
                        </div>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Note pour l\'entrepôt') }}</flux:label>
                        <flux:textarea wire:model="notes" rows="2" :placeholder="__('Emballage fragile, assurance…')" />
                        <flux:error name="notes" />
                    </flux:field>

                    <flux:error name="shipment" />

                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="backToItems">{{ __('Retour') }}</flux:button>
                        <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Demander l\'expédition') }}</flux:button>
                    </div>
                </form>
            @else
                @if ($rescheduling)
                    <flux:text class="text-zinc-500">
                        {{ __('Prévue le :slot. La livraison sera annulée et replanifiée à la nouvelle demi-journée.', ['slot' => $rescheduling->slotLabel()]) }}
                    </flux:text>
                @else
                    <flux:text class="text-zinc-500">{{ __('Choisissez la journée et la demi-journée de livraison.') }}</flux:text>
                @endif

                @if ($payOnDelivery)
                    <flux:badge color="amber" icon="banknotes" class="mt-3">{{ __('Paiement à la livraison : :amount $', ['amount' => $this->requiredAmount()->toDecimal()]) }}</flux:badge>
                @endif

                <form wire:submit="confirm" class="mt-6 space-y-4">
                    <div class="grid grid-cols-7 gap-1 text-center text-xs">
                        @foreach ($this->getSuggestedSlots() as $slotDate => $periods)
                            <div wire:key="slot-{{ $slotDate }}" class="space-y-1">
                                <div class="text-zinc-500">{{ ucfirst(\Illuminate\Support\Carbon::parse($slotDate)->translatedFormat('D j')) }}</div>
                                @foreach ($periods as $periodValue => $halfDay)
                                    @php($remaining = max(0, $halfDay['capacity'] - $halfDay['booked']))
                                    @php($isSelected = $date === $slotDate && $period === $periodValue)
                                    <button
                                        type="button"
                                        wire:click="pickSlot('{{ $slotDate }}', '{{ $periodValue }}')"
                                        @disabled($halfDay['started'] || $remaining === 0)
                                        @class([
                                            'w-full rounded border px-1 py-1 transition-colors',
                                            'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $isSelected,
                                            'border-zinc-200 hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800' => ! $isSelected && ! $halfDay['started'] && $remaining > 0,
                                            'cursor-not-allowed border-zinc-100 text-zinc-300 dark:border-zinc-800 dark:text-zinc-600' => ! $isSelected && ($halfDay['started'] || $remaining === 0),
                                        ])
                                    >
                                        {{ \App\Enums\DeliveryPeriod::from($periodValue)->label() }}
                                        <span class="block">{{ $halfDay['started'] ? '—' : $remaining.'/'.$halfDay['capacity'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-start gap-4">
                        <flux:field>
                            <flux:label>{{ __('Date') }}</flux:label>
                            <flux:input wire:model.live="date" type="date" :min="today()->toDateString()" />
                            <flux:error name="date" />
                        </flux:field>

                        <flux:radio.group wire:model.live="period" :label="__('Demi-journée')" variant="segmented">
                            @foreach (\App\Enums\DeliveryPeriod::cases() as $periodCase)
                                <flux:radio :value="$periodCase->value" :label="$periodCase->label()" />
                            @endforeach
                        </flux:radio.group>
                    </div>
                    <flux:error name="period" />

                    @if (($remaining = $this->selectedSlotRemaining()) !== null)
                        <flux:text @class(['text-sm', 'text-red-600 dark:text-red-400' => $remaining === 0])>
                            {{ trans_choice('Aucune place restante.|:count place restante.|:count places restantes.', $remaining) }}
                        </flux:text>
                    @endif

                    @unless ($rescheduling)
                        <flux:field>
                            <flux:label>{{ __('Note pour le livreur') }}</flux:label>
                            <flux:textarea wire:model="notes" rows="2" :placeholder="__('Code de porte, escalier, appeler avant…')" />
                            <flux:error name="notes" />
                        </flux:field>
                    @endunless

                    <div class="flex justify-end gap-3 pt-2">
                        @if ($rescheduling)
                            <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                        @else
                            <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="backToItems">{{ __('Retour') }}</flux:button>
                        @endif
                        <flux:button type="submit" variant="primary">{{ $rescheduling ? __('Reporter') : __('Planifier la livraison') }}</flux:button>
                    </div>
                </form>
            @endif
        </flux:modal>
    @endcan
</div>
