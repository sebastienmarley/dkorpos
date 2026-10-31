<div class="sm:p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('inventory.counts')" wire:navigate class="text-sm">← {{ __('Prises d\'inventaire') }}</flux:link>
            <flux:heading level="1" size="xl" class="mt-1">{{ __('Prise d\'inventaire du :date — :store', ['date' => $count->counted_on->format('Y-m-d'), 'store' => $count->store?->name ?? '—']) }}</flux:heading>
            <div class="mt-2 flex gap-2">
                <flux:badge :color="$count->status->color()" size="sm">{{ $count->status->label() }}</flux:badge>
                @if ($count->isInProgress())
                    @if ($count->stock_frozen_at)
                        <flux:badge color="sky" size="sm">{{ __('Stock gelé depuis :time', ['time' => $count->stock_frozen_at->format('H:i')]) }}</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">{{ __('Stock non gelé : à compter après la fermeture') }}</flux:badge>
                    @endif
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button icon="arrow-down-tray" wire:click="export">{{ __('Exporter (CSV)') }}</flux:button>
            @can('inventory.supervise')
                <flux:button icon="document-duplicate" :href="route('inventory.counts.sheets', $count)" wire:navigate>{{ __('Feuilles de compte') }}</flux:button>
            @endcan
            @if ($count->isInProgress())
                @can('inventory.adjust')
                    @unless ($count->stock_frozen_at)
                        <flux:button icon="lock-closed" wire:click="freezeStock"
                            wire:confirm="{{ __('Geler le stock ? Plus aucun mouvement ne pourra toucher « En stock » ni « Perdu » (ventes depuis le stock, réceptions, transferts) jusqu\'à la finalisation.') }}">
                            {{ __('Geler le stock') }}
                        </flux:button>
                    @endunless
                    <flux:button variant="ghost" wire:click="abandon"
                        wire:confirm="{{ __('Abandonner cette prise ? Aucun ajustement ne sera passé et le stock sera dégelé.') }}">
                        {{ __('Abandonner') }}
                    </flux:button>
                    <flux:button variant="primary" icon="check" wire:click="finalize"
                        wire:confirm="{{ __('Finaliser la prise ? Chaque écart deviendra un ajustement daté du :date, avec son écriture au grand livre. Ce n\'est pas réversible.', ['date' => $count->counted_on->format('Y-m-d')]) }}">
                        {{ __('Finaliser') }}
                    </flux:button>
                @endcan
            @endif
        </div>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        @foreach ($totals as $status => $total)
            <button type="button" wire:click="$set('type', '{{ $status }}')"
                class="rounded-xl border p-3 text-start {{ $type === $status ? 'border-zinc-800 dark:border-white' : 'border-zinc-200 dark:border-zinc-700' }}">
                <flux:text class="text-xs uppercase text-zinc-500">{{ $total['label'] }}</flux:text>
                <flux:heading size="lg">{{ $total['value']->format() }}</flux:heading>
                <flux:text class="text-xs text-zinc-400">
                    {{ trans_choice(':count ligne|:count lignes', $total['lines']) }}
                    @if ($total['uncounted'] > 0)
                        · <span class="text-amber-600">{{ trans_choice(':count à compter|:count à compter', $total['uncounted']) }}</span>
                    @endif
                </flux:text>
            </button>
        @endforeach
        <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
            <flux:text class="text-xs uppercase text-zinc-500">{{ __('Pertes') }}</flux:text>
            <flux:heading size="lg" class="text-red-600">{{ $losses->format() }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
            <flux:text class="text-xs uppercase text-zinc-500">{{ __('Corrections') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $gains->format() }}</flux:heading>
        </div>
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="lg">{{ $types[$type] ?? '' }}</flux:heading>
        @if ($count->isInProgress())
            <flux:button size="sm" icon="plus" wire:click="openAdd">{{ __('Ajouter un produit trouvé') }}</flux:button>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Produit') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Attendu') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Compté') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Écart') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Valeur') }}</flux:table.column>
                <flux:table.column>{{ __('Note') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($typeLines as $line)
                    <flux:table.row :key="'line-'.$line->id">
                        <flux:table.cell variant="strong">{{ $line->product->display_name }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $line->expected() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if (in_array($line->status->value, $sheetStatuses, true) && ! $line->is_kept)
                                {{ $line->counted_quantity ?? '—' }}
                                <flux:text class="text-xs text-zinc-400">{{ __('feuilles de compte') }}</flux:text>
                            @elseif ($count->isInProgress() && ! $line->is_kept)
                                <div class="ms-auto w-24">
                                    <flux:input size="sm" type="number" min="0" step="1" wire:model.blur="quantities.{{ $line->id }}" />
                                </div>
                            @elseif ($line->is_kept)
                                <flux:badge size="sm" color="zinc">{{ __('Conservée') }}</flux:badge>
                            @else
                                {{ $line->counted_quantity }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="{{ ($line->difference() ?? 0) < 0 ? 'text-red-600' : (($line->difference() ?? 0) > 0 ? 'text-green-600' : '') }}">
                            {{ $line->difference() === null ? '—' : sprintf('%+d', $line->difference()) }}
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $line->differenceValue()?->format() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($count->isInProgress())
                                <flux:input size="sm" wire:model.blur="notes.{{ $line->id }}" :placeholder="__('Variance notée…')" />
                            @else
                                {{ $line->note }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($count->isInProgress())
                                <flux:button size="xs" variant="ghost" wire:click="keep({{ $line->id }})">
                                    {{ $line->is_kept ? __('Compter') : __('Conserver') }}
                                </flux:button>
                            @elseif ($line->adjustment)
                                <flux:text class="text-xs text-zinc-400">{{ $line->adjustment->reason->label() }}</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center">
                            <flux:text class="text-zinc-400">{{ __('Aucune ligne dans ce type de compte.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    <flux:error name="quantities.*" />

    <flux:modal wire:model="showAdd" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Ajouter un produit trouvé') }}</flux:heading>
        <flux:text class="text-zinc-500">{{ __('Le produit est ajouté au type de compte « :type ».', ['type' => $types[$type] ?? '']) }}</flux:text>

        <div class="mt-6 space-y-4">
            <flux:input wire:model.live.debounce.300ms="productSearch" icon="magnifying-glass" :placeholder="__('Rechercher un produit…')" />

            <div class="max-h-72 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @forelse ($productResults as $result)
                    <button type="button" wire:key="product-{{ $result->id }}" wire:click="addProduct({{ $result->id }})"
                        class="flex w-full items-center justify-between px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <span>{{ $result->display_name }}</span>
                    </button>
                @empty
                    <div class="p-4 text-center"><flux:text class="text-zinc-400">{{ __('Aucun produit trouvé.') }}</flux:text></div>
                @endforelse
            </div>
        </div>
    </flux:modal>
</div>
