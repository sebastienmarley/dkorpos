<div class="sm:p-6">
    <div class="mb-6 flex items-center gap-2">
        <flux:heading level="1" size="xl">{{ __('Statistiques') }}</flux:heading>
        <flux:tooltip :content="__('Articles et services remis au client (ramassés, livrés, expédiés, complétés), avant taxes, moins les retours. Le mois en cours va jusqu\'à aujourd\'hui ; il est comparé au mois complet de l\'an dernier.')">
            <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
        </flux:tooltip>
    </div>

    <div class="mb-6 flex flex-wrap items-end gap-3">
        <div class="w-32">
            <flux:select wire:model.live="year" :label="__('Année')">
                @foreach ($years as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($stores->isNotEmpty())
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="storeFilter" :label="__('Magasin')">
                    <flux:select.option value="">{{ __('Tous les magasins') }}</flux:select.option>
                    @foreach ($stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        @if ($sellers->isNotEmpty())
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="sellerFilter" :label="__('Vendeur')">
                    <flux:select.option value="">{{ __('Tout le magasin') }}</flux:select.option>
                    @foreach ($sellers as $seller)
                        <flux:select.option :value="$seller->id">{{ $seller->firstname }} {{ $seller->lastname }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    @if ($subject)
        <flux:heading size="lg" class="mb-3">{{ $subject['label'] }}</flux:heading>

        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Ventes :year à ce jour', ['year' => $year]) }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $total->format() }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Mêmes mois en :year', ['year' => $year - 1]) }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $previousTotal->format() }}</flux:heading>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Mois') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Ventes') }}</flux:table.column>
                    <flux:table.column class="hidden w-1/3 sm:table-cell" />
                    <flux:table.column align="end">{{ __(':year', ['year' => $year - 1]) }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($months as $row)
                        <flux:table.row :key="'month-'.$loop->index">
                            <flux:table.cell variant="strong">{{ $row['month'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row['sales']->format() }}</flux:table.cell>
                            <flux:table.cell class="hidden sm:table-cell">
                                <div class="h-2 rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-2 rounded-full bg-accent" style="width: {{ max(0, round($row['sales']->cents() / $maxSales * 100)) }}%"></div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="text-zinc-500">{{ $row['previous']->format() }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-12 text-center">
                                <flux:text class="text-zinc-400">{{ __('Aucun mois à afficher.') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @else
        <flux:text class="text-zinc-500">{{ __('Aucune statistique de ventes à afficher pour vous.') }}</flux:text>
    @endif
</div>
