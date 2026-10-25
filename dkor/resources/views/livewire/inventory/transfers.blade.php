<div class="mx-auto max-w-5xl p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">{{ __('Transferts') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Marchandise échangée avec les autres magasins. Le magasin qui reçoit demande; celui qui expédie accepte, puis le magasin qui reçoit confirme la réception.') }}</flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($this->canChooseStore())
                <flux:select wire:model.live="storeId" class="w-56">
                    @foreach ($stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @can('transfers.request')
                <flux:button variant="primary" icon="plus" wire:click="openRequest">{{ __('Demander un transfert') }}</flux:button>
            @endcan
        </div>
    </div>

    @php($sections = [
        ['title' => __('À accepter'), 'transfers' => $toAccept, 'role' => 'accept'],
        ['title' => __('En transit vers mon magasin'), 'transfers' => $incoming, 'role' => 'receive'],
        ['title' => __('Demandés par mon magasin'), 'transfers' => $requested, 'role' => 'cancel'],
        ['title' => __('Envoyés, en transit'), 'transfers' => $outgoing, 'role' => null],
    ])

    @foreach ($sections as $section)
        <div class="mb-6" wire:key="section-{{ $loop->index }}">
            <flux:heading size="lg" class="mb-2">{{ $section['title'] }}</flux:heading>
            @forelse ($section['transfers'] as $transfer)
                <div wire:key="transfer-{{ $transfer->id }}" class="mb-2 flex flex-wrap items-start justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-3 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $transfer->quantity }} × {{ $transfer->product->model }}</span>
                            <flux:badge size="sm" :color="$transfer->status->color()">{{ $transfer->status->label() }}</flux:badge>
                            @if ($transfer->source_status === \App\Enums\InventoryStatus::InDemo)
                                <flux:badge size="sm" color="purple">{{ __('Démonstrateur') }}</flux:badge>
                            @endif
                        </div>
                        <div class="text-zinc-500">{{ $transfer->fromStore->name }} → {{ $transfer->toStore->name }} · {{ __('demandé le :date', ['date' => $transfer->requested_at->translatedFormat('j M Y')]) }}</div>
                        @if ($transfer->line)
                            <div class="text-zinc-500">{{ __('Pour la commande #:order (:customer)', ['order' => $transfer->line->customer_order_id, 'customer' => $transfer->line->order->customer->firstname.' '.$transfer->line->order->customer->lastname]) }}</div>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        @if ($section['role'] === 'accept')
                            @can('transfers.accept')
                                <flux:button size="sm" variant="danger" wire:click="openRefuse({{ $transfer->id }})">{{ __('Refuser') }}</flux:button>
                                <flux:button size="sm" variant="primary" icon="check" wire:click="accept({{ $transfer->id }})">{{ __('Accepter') }}</flux:button>
                            @endcan
                        @elseif ($section['role'] === 'receive')
                            @can('transfers.receive')
                                <flux:button size="sm" variant="primary" icon="inbox-arrow-down" wire:click="openReceive({{ $transfer->id }})">{{ __('Confirmer la réception') }}</flux:button>
                            @endcan
                        @elseif ($section['role'] === 'cancel')
                            @can('transfers.request')
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="cancel({{ $transfer->id }})" wire:confirm="{{ __('Annuler ce transfert ?') }}">{{ __('Annuler') }}</flux:button>
                            @endcan
                        @endif
                    </div>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-400">{{ __('Aucun transfert.') }}</flux:text>
            @endforelse
        </div>
    @endforeach

    <flux:heading size="lg" class="mb-2">{{ __('Fermés depuis :days jours', ['days' => \App\Livewire\Inventory\Transfers::RECENT_DAYS]) }}</flux:heading>
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Article') }}</flux:table.column>
                <flux:table.column>{{ __('Magasins') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column>{{ __('Détail') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($closed as $transfer)
                    <flux:table.row :key="'closed-'.$transfer->id">
                        <flux:table.cell variant="strong">{{ $transfer->quantity }} × {{ $transfer->product->model }}</flux:table.cell>
                        <flux:table.cell>{{ $transfer->fromStore->name }} → {{ $transfer->toStore->name }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$transfer->status->color()">{{ $transfer->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>
                            @if ($transfer->status === \App\Enums\StoreTransferStatus::Received)
                                {{ __(':received reçu(s) le :date', ['received' => $transfer->quantity_received, 'date' => $transfer->received_at?->translatedFormat('j M Y')]) }}
                                @if ($transfer->quantity_received < $transfer->quantity)
                                    <span class="text-red-600 dark:text-red-400">· {{ __(':count perdu(s)', ['count' => $transfer->quantity - $transfer->quantity_received]) }}</span>
                                @endif
                            @else
                                {{ $transfer->refusal_reason ?? '—' }}
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-6 text-center text-zinc-400">{{ __('Aucun transfert fermé récemment.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showRequest" class="w-full max-w-lg">
        <form wire:submit="requestTransfer" class="space-y-4">
            <flux:heading>{{ __('Demander un transfert de stock') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('La marchandise « En stock » d\'un autre magasin vient dans le vôtre. Pour un client, demandez le transfert depuis sa commande.') }}</flux:text>

            @if ($selectedProduct)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <span class="font-medium">{{ $selectedProduct->model }}</span>
                    <flux:button size="xs" variant="ghost" wire:click="$set('requestProductId', null)">{{ __('Changer') }}</flux:button>
                </div>

                <flux:field>
                    <flux:label>{{ __('Magasin qui expédie') }}</flux:label>
                    <flux:select wire:model="fromStoreId">
                        @foreach ($this->getOtherStocks() as $stock)
                            <flux:select.option :value="$stock->store_id">{{ __(':store — :count en stock', ['store' => $stock->store->name, 'count' => $stock->quantity_in_stock]) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="fromStoreId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Quantité') }}</flux:label>
                    <flux:input wire:model="quantity" type="number" min="1" />
                    <flux:error name="quantity" />
                </flux:field>
            @else
                <flux:input wire:model.live.debounce.300ms="productSearch" icon="magnifying-glass" :placeholder="__('Modèle du produit…')" />
                <div class="space-y-1">
                    @foreach ($this->getProductResults() as $product)
                        <button type="button" wire:key="result-{{ $product->id }}" wire:click="selectProduct({{ $product->id }})" class="block w-full rounded px-2 py-1 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ $product->model }}</button>
                    @endforeach
                </div>
                <flux:error name="requestProductId" />
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showRequest', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary" :disabled="! $selectedProduct">{{ __('Demander') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showRefuse" class="w-full max-w-md">
        <form wire:submit="refuse" class="space-y-4">
            <flux:heading>{{ __('Refuser le transfert') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Raison') }}</flux:label>
                <flux:input wire:model="refusalReason" required />
                <flux:error name="refusalReason" />
            </flux:field>
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showRefuse', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="danger">{{ __('Refuser') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showReceive" class="w-full max-w-md">
        <form wire:submit="receive" class="space-y-4">
            <flux:heading>{{ __('Confirmer la réception') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('Ce qui manque devient « Perdu » dans votre magasin.') }}</flux:text>
            <flux:field>
                <flux:label>{{ __('Quantité reçue') }}</flux:label>
                <flux:input wire:model="receivedQuantity" type="number" min="0" required />
                <flux:error name="receivedQuantity" />
            </flux:field>
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showReceive', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Confirmer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
