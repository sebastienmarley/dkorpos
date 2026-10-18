<div class="p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">{{ __('Livraisons') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ ucfirst($start->translatedFormat('d F')) }} – {{ $start->copy()->addDays(6)->translatedFormat('d F Y') }}
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:select wire:model.live="storeId" class="w-56">
                @foreach ($stores as $store)
                    <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button.group>
                <flux:button icon="chevron-left" wire:click="previousWeek" :label="__('Semaine précédente')" />
                <flux:button wire:click="goToCurrentWeek">{{ __('Cette semaine') }}</flux:button>
                <flux:button icon="chevron-right" wire:click="nextWeek" :label="__('Semaine suivante')" />
            </flux:button.group>
        </div>
    </div>

    @if ($storeId === '')
        <flux:text class="text-zinc-400">{{ __('Aucun magasin.') }}</flux:text>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid min-w-[56rem] grid-cols-[4rem_repeat(7,minmax(0,1fr))] text-sm">
                <div class="border-b border-zinc-200 dark:border-zinc-700"></div>
                @foreach ($halfDays as $slotDate => $periods)
                    <div wire:key="head-{{ $slotDate }}" @class([
                        'border-b border-s border-zinc-200 px-2 py-2 text-center font-medium dark:border-zinc-700',
                        'bg-sky-50 dark:bg-sky-950' => $slotDate === today()->toDateString(),
                    ])>
                        {{ ucfirst(\Illuminate\Support\Carbon::parse($slotDate)->translatedFormat('D j')) }}
                    </div>
                @endforeach

                @foreach (\App\Enums\DeliveryPeriod::cases() as $period)
                    <div class="border-b border-zinc-200 px-2 py-3 font-medium text-zinc-500 dark:border-zinc-700">{{ $period->label() }}</div>
                    @foreach ($halfDays as $slotDate => $periods)
                        @php($halfDay = $periods[$period->value])
                        <div wire:key="cell-{{ $slotDate }}-{{ $period->value }}" @class([
                            'min-h-28 space-y-1 border-b border-s border-zinc-200 p-2 dark:border-zinc-700',
                            'bg-zinc-50 dark:bg-zinc-800/50' => $halfDay['started'] || $halfDay['capacity'] === 0,
                        ])>
                            <div @class([
                                'text-xs',
                                'text-red-600 dark:text-red-400' => $halfDay['capacity'] > 0 && $halfDay['booked'] >= $halfDay['capacity'],
                                'text-zinc-400' => $halfDay['capacity'] === 0 || $halfDay['booked'] < $halfDay['capacity'],
                            ])>
                                {{ $halfDay['capacity'] === 0 ? __('Aucun camion') : __(':booked / :capacity', ['booked' => $halfDay['booked'], 'capacity' => $halfDay['capacity']]) }}
                            </div>

                            @foreach ($deliveries->filter(fn ($delivery) => $delivery->scheduled_date->toDateString() === $slotDate && $delivery->period === $period) as $delivery)
                                <a
                                    wire:key="delivery-{{ $delivery->id }}"
                                    href="{{ route('customer-orders.show', $delivery->order) }}"
                                    wire:navigate
                                    class="block rounded-md border border-sky-200 bg-sky-50 px-2 py-1 text-xs hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950 dark:hover:bg-sky-900"
                                >
                                    <div class="font-medium text-zinc-900 dark:text-white">
                                        #{{ $delivery->customer_order_id }} · {{ $delivery->order->customer->lastname }}
                                        @if ($delivery->pay_on_delivery)
                                            <flux:icon.banknotes class="inline h-3 w-3 text-amber-500" />
                                        @endif
                                    </div>
                                    <div class="truncate text-zinc-500">{{ $delivery->address_city }}</div>
                                    <div class="truncate text-zinc-400">{{ $delivery->lines->map(fn ($deliveryLine) => $deliveryLine->quantity.' × '.$deliveryLine->line->label)->join(', ') }}</div>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        <flux:text class="mt-3 text-xs text-zinc-400">
            {{ __('Places : livraisons planifiées / capacité des camions qui ont un livreur à l\'horaire. Les camions et leur capacité se gèrent dans la fiche du magasin, onglet Livraison.') }}
        </flux:text>

        <div id="expeditions" class="mt-8">
            <flux:heading size="lg" class="mb-3">{{ __('Expéditions') }}</flux:heading>

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Commande') }}</flux:table.column>
                        <flux:table.column>{{ __('Adresse') }}</flux:table.column>
                        <flux:table.column>{{ __('Articles') }}</flux:table.column>
                        <flux:table.column>{{ __('Statut') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($shipments as $shipment)
                            <flux:table.row :key="'shipment-'.$shipment->id">
                                <flux:table.cell variant="strong">
                                    @can('customer_orders.view')
                                        <flux:link :href="route('customer-orders.show', $shipment->order)" wire:navigate>#{{ $shipment->customer_order_id }}</flux:link>
                                    @else
                                        #{{ $shipment->customer_order_id }}
                                    @endcan
                                    · {{ $shipment->order->customer->firstname }} {{ $shipment->order->customer->lastname }}
                                    @if ($shipment->notes)
                                        <div class="max-w-xs truncate text-xs font-normal text-zinc-400">{{ $shipment->notes }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $shipment->formattedAddress() }}</flux:table.cell>
                                <flux:table.cell>{{ $shipment->lines->map(fn ($shipmentLine) => $shipmentLine->quantity.' × '.$shipmentLine->line->label)->join(', ') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($shipment->ready_at)
                                        <flux:badge size="sm" color="green" icon="check">{{ __('À faire — articles en main depuis le :date', ['date' => $shipment->ready_at->translatedFormat('j M')]) }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc" icon="clock">{{ __('En attente des articles') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="py-8 text-center">
                                    <flux:text class="text-zinc-400">{{ __('Aucune expédition à faire.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    @endif
</div>
