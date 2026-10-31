<div class="sm:p-6">
    <div class="mb-6">
        <flux:link :href="route('receptions.index')" wire:navigate class="text-sm">← {{ __('Réceptions') }}</flux:link>
        <flux:heading level="1" size="xl" class="mt-1">{{ __('Nouvelle réception') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Choisissez un fournisseur, cherchez un bon de commande, cochez les produits attendus et saisissez leur quantité prévue (bordereau du fournisseur), puis commencez la réception : vous compterez ensuite ce qui est réellement reçu.') }}</flux:text>
    </div>

    <form wire:submit="start" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Fournisseur') }}</flux:label>
                <flux:select wire:model.live="supplierId" :placeholder="__('Choisir un fournisseur…')">
                    @foreach ($suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="supplierId" />
            </flux:field>

            @if (filled($supplierId))
                <flux:field>
                    <flux:label>{{ __('N° de bon de commande') }}</flux:label>
                    <flux:input wire:model.live.debounce.300ms="orderSearch" icon="magnifying-glass" autocomplete="off" :placeholder="__('Ex.: CF-000123 ou quote #')" />
                </flux:field>
            @endif
        </div>

        @if (filled($supplierId))
            @if (filled(trim($orderSearch)))
                @forelse ($orders as $order)
                    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900" wire:key="order-{{ $order->id }}">
                        <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                            <div>
                                <flux:link :href="route('supplier-orders.show', $order)" wire:navigate class="font-medium">{{ $order->number }}</flux:link>
                                @if ($order->quote_number)
                                    <flux:text class="text-sm text-zinc-500">{{ __('Quote #') }} {{ $order->quote_number }}</flux:text>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($order->store_id !== auth()->user()->store_id)
                                    <flux:badge color="amber" size="sm" icon="building-storefront">{{ __('Magasin : :store', ['store' => $order->store?->name ?? '—']) }}</flux:badge>
                                @endif
                                <flux:badge :color="$order->status->color()" size="sm">{{ $order->status->label($order->type) }}</flux:badge>
                            </div>
                        </div>

                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column />
                                <flux:table.column>{{ __('Produit') }}</flux:table.column>
                                <flux:table.column align="end">{{ __('À recevoir') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($order->lines->groupBy(fn ($line) => $line->product_id ? 'product-'.$line->product_id : 'line-'.$line->id) as $groupKey => $groupLines)
                                    @php
                                        $groupIds = $groupLines->pluck('id')->all();
                                        $isGroupChecked = $groupLines->every(fn ($line) => $selected[$line->id] ?? false);
                                        $customerCount = $groupLines->filter(fn ($line) => $line->customerOrderLine !== null)->sum('quantity_outstanding');
                                    @endphp
                                    <flux:table.row
                                        :key="$order->id.'-'.$groupKey"
                                        class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                        wire:click="toggleLines({{ json_encode($groupIds) }})"
                                    >
                                        <flux:table.cell>
                                            <flux:checkbox :checked="$isGroupChecked" class="pointer-events-none" wire:key="check-{{ $order->id }}-{{ $groupKey }}-{{ $isGroupChecked ? 'on' : 'off' }}" />
                                        </flux:table.cell>
                                        <flux:table.cell variant="strong">
                                            {{ $groupLines->first()->label }}
                                            @if ($customerCount > 0)
                                                <flux:badge color="blue" size="sm" icon="user" class="ms-2">{{ trans_choice(':count pour client|:count pour clients', $customerCount) }}</flux:badge>
                                            @endif
                                            @if ($groupLines->contains(fn ($line) => $line->status === \App\Enums\SupplierOrderLineStatus::CancellationRequested))
                                                <flux:badge :color="\App\Enums\SupplierOrderLineStatus::CancellationRequested->color()" size="sm" class="ms-2">{{ \App\Enums\SupplierOrderLineStatus::CancellationRequested->label() }}</flux:badge>
                                            @endif
                                            @foreach ($groupLines as $line)
                                                @if ($shipment = $line->activeShipment())
                                                    <flux:badge color="amber" size="sm" icon="paper-airplane" class="ms-2">{{ __('Pour expédition — commande #:order', ['order' => $shipment->customer_order_id]) }}</flux:badge>
                                                @endif
                                            @endforeach
                                        </flux:table.cell>
                                        <flux:table.cell align="end">{{ $groupLines->sum('quantity_outstanding') }} / {{ $groupLines->sum('quantity') }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                        <flux:text class="text-zinc-400">{{ __('Aucun bon de commande à recevoir ne correspond à cette recherche.') }}</flux:text>
                    </div>
                @endforelse
            @else
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                    <flux:text class="text-zinc-400">{{ __('Saisissez un numéro de bon de commande (ou un quote #) pour afficher ses lignes.') }}</flux:text>
                </div>
            @endif

            {{-- Sélection courante, toutes recherches confondues : quantité prévue selon le bordereau du fournisseur --}}
            @if ($selectedGroups->isNotEmpty())
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('À recevoir') }} ({{ $selectedGroups->count() }})</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Saisissez la quantité prévue de chaque produit selon le bordereau du fournisseur.') }}</flux:text>
                    <div class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($selectedGroups as $groupKey => $groupLines)
                            <div class="flex flex-wrap items-center justify-between gap-3 py-2" wire:key="selected-{{ $groupKey }}">
                                <div class="min-w-0">
                                    <flux:text class="font-medium">{{ $groupLines->first()->label }}</flux:text>
                                    <flux:text class="text-xs text-zinc-400">
                                        {{ $groupLines->map(fn ($line) => $line->order->number)->unique()->join(', ') }}
                                        · {{ __('En commande : :count', ['count' => $groupLines->sum('quantity_outstanding')]) }}
                                    </flux:text>
                                    <flux:error name="expected.{{ $groupKey }}" />
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:input type="number" min="0" :max="$groupLines->sum('quantity_outstanding')" size="sm" class="w-24" wire:model="expected.{{ $groupKey }}" :aria-label="__('Quantité prévue')" />
                                    <flux:button type="button" size="xs" variant="ghost" icon="x-mark" wire:click="removeSelected({{ json_encode($groupLines->pluck('id')->all()) }})" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button :href="route('receptions.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="inbox-arrow-down" :disabled="$selectedGroups->isEmpty()">{{ __('Commencer la réception') }}</flux:button>
            </div>
        @endif
    </form>

    {{-- Confirmation : commande destinée à un autre magasin (stores.all) --}}
    <flux:modal wire:model="showStoreConfirm" class="w-full max-w-md">
        @if ($pendingLine)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Commande d\'un autre magasin') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('La commande :number est celle du magasin :store. Êtes-vous certain de vouloir la recevoir ?', ['number' => $pendingLine->order->number, 'store' => $pendingLine->order->store?->name ?? '—']) }}
                    </flux:text>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelOtherStore">{{ __('Annuler') }}</flux:button>
                    <flux:button type="button" variant="primary" wire:click="confirmOtherStore">{{ __('Oui, la recevoir') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
