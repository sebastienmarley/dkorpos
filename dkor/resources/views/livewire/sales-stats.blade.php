<div class="space-y-8">
    @if ($ownSales)
        <section>
            <div class="mb-3 flex items-center gap-2">
                <flux:heading size="lg">{{ __('Mes ventes') }}</flux:heading>
                <flux:tooltip :content="__('Articles et services remis au client (ramassés, livrés, expédiés, complétés), avant taxes, moins les retours.')">
                    <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                </flux:tooltip>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($ownSales as $label => $amount)
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-sm text-zinc-500">{{ $label }}</flux:text>
                        <flux:heading size="xl" class="mt-1">{{ $amount->format() }}</flux:heading>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($storeSales)
        <section>
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('Ventes du magasin') }} — {{ $storeSales['name'] }}</flux:heading>
                    <flux:tooltip :content="__('Articles et services remis au client (ramassés, livrés, expédiés, complétés), avant taxes, moins les retours.')">
                        <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                    </flux:tooltip>
                </div>

                @if ($stores->isNotEmpty())
                    <div class="w-full sm:w-56">
                        <flux:select wire:model.live="storeFilter" size="sm">
                            <flux:select.option value="">{{ __('Tous les magasins') }}</flux:select.option>
                            @foreach ($stores as $store)
                                <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($storeSales['sales'] as $label => $amount)
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-sm text-zinc-500">{{ $label }}</flux:text>
                        <flux:heading size="xl" class="mt-1">{{ $amount->format() }}</flux:heading>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
