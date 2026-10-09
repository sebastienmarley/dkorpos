<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Journal d\'inventaire') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                @if ($filteredProduct)
                    {{ __('Mouvements de :product', ['product' => $filteredProduct->display_name]) }}
                    · <flux:link wire:click="$set('productFilter', '')" class="cursor-pointer">{{ __('Tous les produits') }}</flux:link>
                @else
                    {{ __('Tous les mouvements entre états d\'inventaire') }}
                @endif
            </flux:text>
        </div>

        @can('inventory.move')
            <flux:button variant="primary" icon="arrows-right-left" wire:click="openMove">{{ __('Nouveau mouvement') }}</flux:button>
        @endcan
    </div>

    <div class="mb-4 flex gap-3">
        <div class="w-56">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="">{{ __('Tous les états') }}</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-64">
            <flux:select wire:model.live="typeFilter">
                <flux:select.option value="">{{ __('Tous les types') }}</flux:select.option>
                @foreach ($types as $type)
                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$movements">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Produit') }}</flux:table.column>
                <flux:table.column>{{ __('De') }}</flux:table.column>
                <flux:table.column>{{ __('Vers') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Qté') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Référence') }}</flux:table.column>
                <flux:table.column>{{ __('Par') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($movements as $movement)
                    <flux:table.row :key="$movement->id">
                        <flux:table.cell>{{ $movement->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('products.show', $movement->product)" wire:navigate>{{ $movement->product->display_name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($movement->from_status)
                                <flux:badge :color="$movement->from_status->color()" size="sm">{{ $movement->from_status->label() }}</flux:badge>
                            @else
                                <span class="text-zinc-400">{{ __('Extérieur') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($movement->to_status)
                                <flux:badge :color="$movement->to_status->color()" size="sm">{{ $movement->to_status->label() }}</flux:badge>
                            @else
                                <span class="text-zinc-400">{{ __('Sortie') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $movement->quantity }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $movement->type->label() }}
                            @if ($movement->note)
                                <flux:text class="text-xs text-zinc-400">{{ $movement->note }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($movement->reference instanceof \App\Models\SupplierOrderLine)
                                <flux:link :href="route('supplier-orders.show', $movement->reference->order)" wire:navigate>{{ $movement->reference->order->number }}</flux:link>
                            @elseif ($movement->reference instanceof \App\Models\ReceptionLine)
                                <flux:link :href="route('receptions.show', $movement->reference->reception)" wire:navigate>{{ $movement->reference->reception->number }}</flux:link>
                            @elseif ($movement->reference instanceof \App\Models\CustomerOrder)
                                <flux:link :href="route('customer-orders.show', $movement->reference)" wire:navigate>{{ __('Commande client #:id', ['id' => $movement->reference->id]) }}</flux:link>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $movement->user ? $movement->user->firstname.' '.$movement->user->lastname : '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="clipboard-document-list" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucun mouvement d\'inventaire.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-3">
        <flux:text class="text-sm text-zinc-400">{{ trans_choice(':count mouvement|:count mouvements', $movements->total()) }}</flux:text>
    </div>

    {{-- Nouveau mouvement --}}
    <flux:modal wire:model="showMove" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Nouveau mouvement d\'inventaire') }}</flux:heading>
        <flux:text class="text-zinc-500">{{ __('Déplace une quantité d\'un état à un autre. Les quantités « en commande » suivent les commandes fournisseurs.') }}</flux:text>

        <div class="mt-6 space-y-4">
            @if ($selectedProduct)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div>
                        <flux:text class="font-medium">{{ $selectedProduct->display_name }}</flux:text>
                        @if ($selectedProduct->inventoryStock)
                            <flux:text class="text-xs text-zinc-400">
                                @foreach ($movableStatuses as $status)
                                    {{ $status->label() }} : {{ $selectedProduct->inventoryStock->quantityFor($status) }}{{ ! $loop->last ? ' · ' : '' }}
                                @endforeach
                            </flux:text>
                        @endif
                    </div>
                    <flux:button size="xs" variant="ghost" wire:click="$set('moveProductId', null)">{{ __('Changer') }}</flux:button>
                </div>
                <flux:error name="moveProductId" />

                <form wire:submit="move" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="fromStatus" :label="__('De')">
                            @foreach ($movableStatuses as $status)
                                <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="toStatus" :label="__('Vers')">
                            @foreach ($movableStatuses as $status)
                                <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:error name="fromStatus" />
                    <flux:error name="toStatus" />

                    <flux:field>
                        <flux:label>{{ __('Quantité') }}</flux:label>
                        <flux:input wire:model="quantity" type="number" min="1" step="1" required />
                        <flux:error name="quantity" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Note (optionnel)') }}</flux:label>
                        <flux:input wire:model="note" type="text" />
                        <flux:error name="note" />
                    </flux:field>

                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showMove', false)">{{ __('Annuler') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
                    </div>
                </form>
            @else
                <flux:input wire:model.live.debounce.300ms="productSearch" icon="magnifying-glass" :placeholder="__('Rechercher un produit…')" />

                <div class="max-h-72 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($productResults as $result)
                        <button type="button" wire:key="product-{{ $result->id }}" wire:click="selectProduct({{ $result->id }})"
                            class="flex w-full items-center justify-between px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span>{{ $result->display_name }}</span>
                        </button>
                    @empty
                        <div class="p-4 text-center"><flux:text class="text-zinc-400">{{ __('Aucun produit trouvé.') }}</flux:text></div>
                    @endforelse
                </div>
            @endif
        </div>
    </flux:modal>
</div>
