<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Pièces') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Pièces de remplacement commandées pour les clients') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-part-picker')">
            {{ __('Ajouter une pièce') }}
        </flux:button>
    </div>

    {{-- Onglets --}}
    <div class="mb-4 flex border-b border-zinc-200 dark:border-zinc-700">
        @foreach (['catalog' => __('Catalogue'), 'orders' => __('Commandes fournisseur')] as $key => $label)
            <button
                type="button"
                wire:click="$set('tab', '{{ $key }}')"
                @class([
                    '-mb-px px-4 py-3 text-sm font-medium transition-colors',
                    'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' => $tab === $key,
                    'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' => $tab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'orders')
        {{-- Commandes fournisseur --}}
        <div class="mb-4 flex gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="orderSearch" placeholder="{{ __('Rechercher par modèle, description, client ou numéro de commande…') }}" icon="magnifying-glass" />
            </div>

            <div class="w-56">
                <flux:select wire:model.live="orderStage">
                    <flux:select.option value="">{{ __('Tous les statuts') }}</flux:select.option>
                    @foreach ($orderStages as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table :paginate="$partOrders">
                <flux:table.columns>
                    <flux:table.column>{{ __('Pièce') }}</flux:table.column>
                    <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                    <flux:table.column>{{ __('Commande fournisseur') }}</flux:table.column>
                    <flux:table.column>{{ __('Client') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Qté') }}</flux:table.column>
                    <flux:table.column>{{ __('Statut') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($partOrders as $line)
                        <flux:table.row :key="$line->id">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('parts.show', $line->part)" wire:navigate>{{ $line->part->model }}</flux:link>
                                <div class="max-w-xs truncate text-xs font-normal text-zinc-500">{{ $line->part->description }}</div>
                            </flux:table.cell>

                            <flux:table.cell>{{ $line->part->supplier->name }}</flux:table.cell>

                            <flux:table.cell>
                                @if ($supplierOrder = $line->supplierOrderLine?->order)
                                    @can('supplier_orders.view')
                                        <flux:link :href="route('supplier-orders.show', $supplierOrder)" wire:navigate>{{ $supplierOrder->number }}</flux:link>
                                    @else
                                        {{ $supplierOrder->number }}
                                    @endcan
                                    <div class="text-xs text-zinc-400">{{ $supplierOrder->status->label() }}</div>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $line->order->customer->firstname }} {{ $line->order->customer->lastname }}
                                <div class="text-xs">
                                    @can('customer_orders.view')
                                        <flux:link :href="route('customer-orders.show', $line->order)" wire:navigate>{{ __('Commande client #:id', ['id' => $line->order->id]) }}</flux:link>
                                    @else
                                        <span class="text-zinc-400">{{ __('Commande client #:id', ['id' => $line->order->id]) }}</span>
                                    @endcan
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="end">{{ $line->quantity }}</flux:table.cell>

                            <flux:table.cell>
                                <flux:badge :color="$line->status->color()" size="sm">{{ $line->status->label() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon name="truck" class="h-8 w-8 text-zinc-300" />
                                    @if (filled($orderSearch) || filled($orderStage))
                                        <flux:text class="text-zinc-400">{{ __('Aucune pièce commandée pour ces critères.') }}</flux:text>
                                    @else
                                        <flux:text class="text-zinc-400">{{ __('Aucune pièce commandée. Ajoutez une pièce depuis une commande client.') }}</flux:text>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="mt-3">
            <flux:text class="text-sm text-zinc-400">
                {{ trans_choice(':count pièce commandée|:count pièces commandées', $partOrders->total()) }}
            </flux:text>
        </div>
    @else
        {{-- Catalogue --}}
        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher par modèle, description ou produit (SKU)…') }}" icon="magnifying-glass" />
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table :paginate="$parts">
                <flux:table.columns>
                    <flux:table.column>{{ __('Modèle') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                    <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                    <flux:table.column>{{ __('Produits') }}</flux:table.column>
                    <flux:table.column>{{ __('Dernier coût') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($parts as $part)
                        <flux:table.row :key="$part->id">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('parts.show', $part)" wire:navigate>
                                    {{ $part->model }}
                                </flux:link>
                            </flux:table.cell>

                            <flux:table.cell>{{ $part->description }}</flux:table.cell>

                            <flux:table.cell>{{ $part->supplier->name }}</flux:table.cell>

                            <flux:table.cell>
                                @if ($part->products->isEmpty())
                                    <span class="text-zinc-400">—</span>
                                @else
                                    {{ $part->products->pluck('model')->join(', ') }}
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>{{ number_format($part->last_cost, 2) }} $</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon name="puzzle-piece" class="h-8 w-8 text-zinc-300" />
                                    @if (filled($search))
                                        <flux:text class="text-zinc-400">{{ __('Aucune pièce trouvée pour ces critères.') }}</flux:text>
                                    @else
                                        <flux:text class="text-zinc-400">{{ __('Aucune pièce. Cliquez sur « Ajouter une pièce » pour commencer.') }}</flux:text>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="mt-3">
            <flux:text class="text-sm text-zinc-400">
                {{ trans_choice(':count pièce|:count pièces', $parts->total()) }}
            </flux:text>
        </div>
    @endif

    <livewire:part-picker />
</div>
