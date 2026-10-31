<div class="sm:p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">{{ __('Livraisons') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ ucfirst($start->translatedFormat('d F')) }} – {{ $start->copy()->addDays(6)->translatedFormat('d F Y') }}
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($this->canChooseStore())
                <flux:select wire:model.live="storeId" class="w-full sm:w-56">
                    @foreach ($stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
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

        @can('shipments.manage')
            <flux:text class="mt-6 text-sm">
                <flux:link :href="route('shipments.index', ['store' => $storeId])" wire:navigate>{{ __('Voir les expéditions à faire →') }}</flux:link>
            </flux:text>
        @endcan
    @endif
</div>
