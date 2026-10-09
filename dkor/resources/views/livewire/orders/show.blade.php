@php
    use App\Enums\SupplierOrderLineStatus as LineStatus;
    use App\Enums\SupplierOrderStatus as Status;

    $isProduct = $order->isProductOrder();
    $status = $order->status;
@endphp

<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:link :href="route('supplier-orders.index')" wire:navigate class="text-sm">
                ← {{ __('Commandes fournisseurs') }}
            </flux:link>
            <div class="mt-1 flex items-center gap-3">
                <flux:heading level="1" size="xl">{{ $order->number }}</flux:heading>
                <flux:badge :color="$order->type->color()" size="sm">{{ $order->type->label() }}</flux:badge>
                <flux:badge :color="$status->color()" size="sm">{{ $status->label($order->type) }}</flux:badge>
                @if ($order->quote_number)
                    <flux:text class="text-zinc-500">{{ __('Quote #') }} {{ $order->quote_number }}</flux:text>
                @endif
            </div>
            <flux:text class="mt-1 text-zinc-500">
                <flux:link :href="route('suppliers.show', $order->supplier)" wire:navigate>{{ $order->supplier->name }}</flux:link>
                · {{ __('Créée le :date', ['date' => $order->created_at->format('Y-m-d')]) }}
                @if ($order->creator)
                    {{ __('par :name', ['name' => $order->creator->firstname.' '.$order->creator->lastname]) }}
                @endif
                @if ($order->sent_at)
                    · {{ __('Envoyée le :date', ['date' => $order->sent_at->format('Y-m-d H:i')]) }}
                    @if ($order->last_emailed_at || \App\Models\SupplierOrder::emailEnabled())
                        · {{ $order->last_emailed_at
                            ? __('Dernier courriel le :date', ['date' => $order->last_emailed_at->format('Y-m-d H:i')])
                            : __('Aucun courriel envoyé') }}
                    @endif
                @endif
            </flux:text>
        </div>

        <div class="flex gap-2">
            @if ($order->isDeletable())
                @can('supplier_orders.delete')
                    <flux:button variant="ghost" icon="trash" wire:click="deleteOrder" wire:confirm="{{ __('Supprimer définitivement cette commande vide?') }}">
                        {{ __('Supprimer') }}
                    </flux:button>
                @endcan
            @endif
        @can('supplier_orders.edit')
                @if ($status === Status::Draft)
                    <flux:button icon="clock" wire:click="markPending" wire:confirm="{{ __('Mettre cette commande en attente d\'envoi? Elle ne sera plus un brouillon.') }}">
                        {{ __('Mettre en attente') }}
                    </flux:button>
                @endif

                @if ($status->isEditable())
                    <flux:button variant="primary" icon="paper-airplane" wire:click="send" wire:confirm="{{ __('Fermer et envoyer cette commande? Les quantités passeront « en commande ».') }}">
                        {{ __('Fermer et envoyer') }}
                    </flux:button>
                @endif

                @if ($status->isOpen() && \App\Models\SupplierOrder::emailEnabled())
                    <flux:button variant="ghost" icon="envelope" wire:click="resendEmail" wire:confirm="{{ __('Renvoyer la commande par courriel au fournisseur?') }}">
                        {{ __('Renvoyer le courriel') }}
                    </flux:button>
                @endif

                @if ($isProduct && $status->isOpen())
                    <flux:button variant="primary" icon="inbox-arrow-down" wire:click="openReceive">
                        {{ __('Réceptionner') }}
                    </flux:button>
                @endif

                @if (! $isProduct && $status === Status::Sent)
                    <flux:button variant="primary" icon="check" wire:click="complete">
                        {{ __('Marquer complétée') }}
                    </flux:button>
                @endif

                @if (($isProduct && $receptionLines->isNotEmpty()) || (! $isProduct && in_array($status, [Status::Received, Status::Invoiced], true)))
                    @can('invoices.view')
                        <flux:button icon="document-text" :href="route('accounting.invoices', ['search' => $order->number])" wire:navigate>
                            {{ __('Facturation') }}
                        </flux:button>
                    @endcan
                @endif

                @if ($status->isEditable() || $status->isOpen())
                    <flux:button variant="danger" icon="x-mark" wire:click="cancel" wire:confirm="{{ __('Annuler cette commande?') }}">
                        {{ __('Annuler') }}
                    </flux:button>
                @endif
        @endcan
        </div>
    </div>

    {{-- Lignes --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ $isProduct ? __('Produit') : __('Description') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Quantité') }}</flux:table.column>
                @if ($isProduct && ! $status->isEditable())
                    <flux:table.column align="end">{{ __('Reçue') }}</flux:table.column>
                @endif
                <flux:table.column align="end">{{ __('Coût unitaire') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                @if ($status->isEditable() || $status->isOpen())
                    <flux:table.column />
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($order->lines as $line)
                    <flux:table.row :key="$line->id">
                        <flux:table.cell variant="strong" @class(['line-through opacity-60' => $line->status->isClosed()])>
                            @if ($line->product)
                                <flux:link :href="route('products.show', $line->product)" wire:navigate>{{ $line->label }}</flux:link>
                            @else
                                {{ $line->label }}
                            @endif
                            @if ($line->status !== LineStatus::Active)
                                <flux:badge :color="$line->status->color()" size="sm" class="ms-2">{{ $line->status->label() }}</flux:badge>
                            @endif
                            @if ($line->customerOrderLine)
                                <flux:text class="text-xs text-zinc-400">
                                    {{ __('Client :') }}
                                    <flux:link :href="route('customer-orders.show', $line->customerOrderLine->order)" wire:navigate class="text-xs">
                                        {{ $line->customerOrderLine->order->customer->firstname }} {{ $line->customerOrderLine->order->customer->lastname }} — {{ __('commande #:id', ['id' => $line->customerOrderLine->customer_order_id]) }}
                                    </flux:link>
                                </flux:text>
                            @endif
                            @if ($line->substitutedFrom)
                                <flux:text class="text-xs text-zinc-400">{{ __('Substitut de :product', ['product' => $line->substitutedFrom->label]) }}</flux:text>
                            @endif
                            @if ($line->status === LineStatus::Substituted && $line->substitutedBy)
                                <flux:text class="text-xs text-zinc-400">{{ __('Remplacé par :product', ['product' => $line->substitutedBy->label]) }}</flux:text>
                            @endif
                            @if ($line->cancellation_reason && $line->status === LineStatus::CancellationRequested)
                                <flux:text class="text-xs text-zinc-400">{{ $line->cancellation_reason }}</flux:text>
                            @endif
                        </flux:table.cell>
                        @if ($editingLineId === $line->id)
                            <flux:table.cell align="end">
                                <flux:input wire:model="editQuantity" type="number" min="{{ max(1, $line->quantity_received) }}" step="1" class="w-24" />
                                <flux:error name="editQuantity" />
                            </flux:table.cell>
                            @if ($isProduct && ! $status->isEditable())
                                <flux:table.cell align="end">{{ $line->quantity_received }}</flux:table.cell>
                            @endif
                            <flux:table.cell align="end">
                                <flux:input wire:model="editUnitCost" type="number" min="0" step="0.01" class="w-28" />
                                <flux:error name="editUnitCost" />
                            </flux:table.cell>
                            <flux:table.cell align="end">—</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="primary" icon="check" wire:click="saveLine" />
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="cancelEditLine" />
                            </flux:table.cell>
                        @else
                            <flux:table.cell align="end">{{ $line->quantity }}</flux:table.cell>
                            @if ($isProduct && ! $status->isEditable())
                                <flux:table.cell align="end">{{ $line->quantity_received }}</flux:table.cell>
                            @endif
                            <flux:table.cell align="end">{{ number_format($line->unit_cost, 2) }} $</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($line->total, 2) }} $</flux:table.cell>
                            @if ($status->isEditable() || $status->isOpen())
                                <flux:table.cell align="end">
                                    @can('supplier_orders.edit')
                                        @if ($line->status === LineStatus::CancellationRequested)
                                            <flux:button size="xs" variant="primary" wire:click="confirmLineCancellation({{ $line->id }})" wire:confirm="{{ __('Le fournisseur a confirmé l\'annulation?') }}">{{ __('Confirmée') }}</flux:button>
                                            <flux:button size="xs" variant="ghost" wire:click="rejectLineCancellation({{ $line->id }})">{{ __('Refusée') }}</flux:button>
                                        @elseif ($line->status->isClosed())
                                            {{-- aucune action --}}
                                        @else
                                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="startEditLine({{ $line->id }})" />
                                        @if ($status->isOpen() && $line->quantity_outstanding > 0)
                                            <flux:button size="xs" variant="ghost" icon="no-symbol" x-on:click="$dispatch('open-supplier-line-cancellation', { lineId: {{ $line->id }} })" :title="__('Demander l\'annulation')" />
                                            @if ($isProduct)
                                                <flux:button size="xs" variant="ghost" icon="arrows-right-left" wire:click="openSubstitute({{ $line->id }})" />
                                            @endif
                                        @endif
                                        @if ($status->isEditable())
                                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeLine({{ $line->id }})" />
                                        @endif
                                        @endif
                                    @endcan
                                </flux:table.cell>
                            @endif
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center">
                            <flux:text class="text-zinc-400">{{ __('Aucune ligne pour le moment.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Ajout d'une ligne --}}
        @if ($status->isEditable() || $status->isOpen())
            @can('supplier_orders.edit')
                <form wire:submit="addLine" class="flex flex-wrap items-start gap-3 border-t border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="min-w-64 flex-1">
                        @if ($isProduct)
                            @if ($selectedProduct)
                                <div class="flex items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                    <span>{{ $selectedProduct->display_name }} <span class="text-xs text-zinc-400">#{{ $selectedProduct->id }}</span></span>
                                    <flux:button type="button" size="xs" variant="ghost" wire:click="clearProduct">{{ __('Changer') }}</flux:button>
                                </div>
                            @else
                                <div class="relative" data-line-search x-on:keydown.enter.prevent="$wire.selectFirstProduct($event.target.value)">
                                    <flux:input wire:model.live.debounce.300ms="productSearch" icon="magnifying-glass" autocomplete="off" :placeholder="__('Rechercher un produit (id ou modèle)…')" />

                                    @if (filled($productSearch))
                                        <div class="absolute z-10 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                            @forelse ($productResults as $result)
                                                <button type="button" wire:key="product-result-{{ $result->id }}" wire:click="selectProduct({{ $result->id }})"
                                                    class="flex w-full items-center justify-between px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                                    <span>{{ $result->display_name }}</span>
                                                    <span class="text-sm text-zinc-400">#{{ $result->id }} · {{ number_format($result->cost, 2) }} $</span>
                                                </button>
                                            @empty
                                                <div class="p-3 text-center"><flux:text class="text-zinc-400">{{ __('Aucun produit trouvé.') }}</flux:text></div>
                                            @endforelse
                                        </div>
                                    @endif
                                </div>
                            @endif
                            <flux:error name="productId" />
                        @else
                            <div data-line-search>
                                <flux:input wire:model="description" :placeholder="__('Description du service')" />
                            </div>
                            <flux:error name="description" />
                        @endif
                    </div>
                    <div class="w-28" data-line-quantity>
                        <flux:input wire:model="quantity" type="number" min="1" step="1" :placeholder="__('Qté')" />
                        <flux:error name="quantity" />
                    </div>
                    <div class="w-36">
                        <flux:input wire:model="unitCost" type="number" min="0" step="0.01" :placeholder="__('Coût unitaire')" />
                        <flux:error name="unitCost" />
                    </div>
                    <flux:button type="submit" icon="plus">{{ __('Ajouter') }}</flux:button>
                </form>
            @endcan
        @endif
    </div>

    <div class="mt-3 text-end">
        <flux:text class="text-lg font-semibold">{{ __('Total') }} : {{ number_format($order->total, 2) }} $</flux:text>
    </div>

    {{-- Réceptions (produits) --}}
    @if ($isProduct && $receptionLines->isNotEmpty())
        @php $canReverse = in_array($status, [Status::Sent, Status::PartiallyReceived, Status::Received], true); @endphp
        <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="px-4 pt-4">
                <flux:heading size="lg">{{ __('Réceptions') }}</flux:heading>
                @if (! $canReverse)
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Les réceptions facturées ne peuvent plus être renversées.') }}</flux:text>
                @endif
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Réception') }}</flux:table.column>
                    <flux:table.column>{{ __('Produit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Reçu') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Renversé') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Coût') }}</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($receptionLines as $receptionLine)
                        <flux:table.row :key="$receptionLine->id">
                            <flux:table.cell>
                                <flux:link :href="route('receptions.show', $receptionLine->reception)" wire:navigate>{{ $receptionLine->reception->number }}</flux:link>
                                <flux:text class="text-xs text-zinc-400">{{ $receptionLine->reception->received_at->format('Y-m-d H:i') }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>{{ $receptionLine->orderLine->label }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $receptionLine->quantity }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($receptionLine->quantity_reversed > 0)
                                    <span title="{{ $receptionLine->reversal_reason }}">{{ $receptionLine->quantity_reversed }}</span>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($receptionLine->unit_cost, 2) }} $</flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($canReverse && $receptionLine->quantity_net > 0 && ! $receptionLine->reception->invoice)
                                    @can('receptions.reverse')
                                        <flux:button size="xs" variant="ghost" icon="arrow-uturn-left" wire:click="openReverse({{ $receptionLine->id }})">{{ __('Renverser') }}</flux:button>
                                    @endcan
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    {{-- Transport (produits) --}}
    @if ($isProduct)
        @php
            $threshold = $order->prepaidThreshold();
            $orderTotal = $order->total;
            $ratio = $threshold > 0 ? min(100, round($orderTotal / $threshold * 100)) : 100;
        @endphp
        <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Transport') }}</flux:heading>

            @if ($order->isCollectShipping())
                <flux:text class="mt-2">
                    {{ __('Expédition collect') }}
                    @if ($order->shippingSupplier)
                        — {{ $order->shippingSupplier->name }}
                    @endif
                </flux:text>
            @elseif ($threshold > 0)
                <div class="mt-2 flex items-center justify-between">
                    <flux:text>{{ number_format($orderTotal, 2) }} $ / {{ number_format($threshold, 2) }} $ {{ __('(transport prépayé)') }}</flux:text>
                    @if ($order->meetsPrepaidThreshold())
                        <flux:badge color="green" size="sm">{{ __('Transport prépayé') }}</flux:badge>
                    @else
                        <flux:badge color="amber" size="sm">{{ __(':amount $ manquants', ['amount' => number_format($order->missingForPrepaid(), 2)]) }}</flux:badge>
                    @endif
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-full {{ $order->meetsPrepaidThreshold() ? 'bg-green-500' : 'bg-amber-500' }}" style="width: {{ $ratio }}%"></div>
                </div>
            @else
                <flux:text class="mt-2">{{ __('Transport prépayé (aucun montant minimum).') }}</flux:text>
            @endif

            @if ($status->isEditable())
                @can('supplier_orders.edit')
                    <form wire:submit="saveShipping" class="mt-4 flex flex-wrap items-end gap-4">
                        <flux:checkbox wire:model.live="isCollect" :label="__('Envoyer collect')" :disabled="$order->supplier->collect" />

                        @if ($isCollect)
                            <div class="w-64">
                                <flux:select wire:model="shippingSupplierId" :label="__('Fournisseur d\'expédition')" :placeholder="__('Choisir…')">
                                    @foreach ($shippingSuppliers as $shippingSupplier)
                                        <flux:select.option :value="$shippingSupplier->id">{{ $shippingSupplier->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="shippingSupplierId" />
                            </div>
                        @endif

                        <flux:button type="submit">{{ __('Sauvegarder le transport') }}</flux:button>
                    </form>
                @endcan
            @endif
        </div>
    @endif

    {{-- Drop ship (produits) --}}
    @if ($isProduct && ($status->isEditable() || $order->is_drop_ship))
        <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Livraison') }}</flux:heading>

            @if (! $status->isEditable())
                <flux:text class="mt-2">{{ __('Drop ship') }} — {{ $order->drop_ship_name }}</flux:text>
                <flux:text class="whitespace-pre-line text-zinc-500">{{ $order->dropShipAddressLabel() }}</flux:text>
            @else
                @can('supplier_orders.edit')
                    <form wire:submit="saveDropShip" class="mt-4 space-y-4">
                        <flux:checkbox wire:model.live="isDropShip" :label="__('Drop ship (livrer à une adresse différente de celle du marchand)')" />

                        @if ($isDropShip)
                            <flux:field>
                                <flux:label>{{ __('Nom du destinataire') }}</flux:label>
                                <flux:input wire:model="dropShipName" type="text" />
                                <flux:error name="dropShipName" />
                            </flux:field>

                            <x-address-input prefix="dropShipAddress" :current-country="$dropShipAddress['country'] ?? 'CA'" />
                        @endif

                        <flux:button type="submit">{{ __('Sauvegarder la livraison') }}</flux:button>
                    </form>
                @endcan
            @endif
        </div>
    @endif

    {{-- Facture --}}
    @if ($invoices->isNotEmpty())
        <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Factures du fournisseur') }}</flux:heading>
            <div class="mt-2 divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($invoices as $invoice)
                    <div class="flex items-center justify-between py-2">
                        <flux:text>
                            <flux:link :href="$invoice->reception ? route('accounting.invoices.reception', $invoice->reception) : route('accounting.invoices.order', $order)" wire:navigate>{{ $invoice->reception?->number ?? $order->number }}</flux:link>
                            · {{ __('Facture n° :number du :date', ['number' => $invoice->invoice_number, 'date' => $invoice->invoice_date->format('Y-m-d')]) }}
                        </flux:text>
                        <flux:text>
                            {{ number_format($invoice->invoice_total, 2) }} $
                            @if ($invoice->hasVariance())
                                <flux:badge color="red" size="sm" class="ms-2">{{ __('Écart') }} {{ number_format($invoice->variance, 2) }} $</flux:badge>
                            @endif
                        </flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endif


    {{-- Notes --}}
    <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit="saveNotes" class="space-y-3">
            <flux:field>
                <flux:label>{{ __('Quote #') }}</flux:label>
                <flux:input wire:model="quoteNumber" type="text" :disabled="! auth()->user()->can('supplier_orders.edit')" />
                <flux:error name="quoteNumber" />
            </flux:field>
            <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" :disabled="! auth()->user()->can('supplier_orders.edit')" />
            <flux:error name="notes" />
            @can('supplier_orders.edit')
                <flux:button type="submit">{{ __('Sauvegarder') }}</flux:button>
            @endcan
        </form>
    </div>

    {{-- Renversement d'une réception --}}
    <flux:modal wire:model="showReverse" class="w-full max-w-md">
        <flux:heading class="mb-1">{{ __('Renverser une réception') }}</flux:heading>
        <flux:text class="text-zinc-500">{{ __('Les unités reçues sont retirées de l\'inventaire et la quantité revient « en commande ». Impossible si elles ont déjà été livrées.') }}</flux:text>

        <form wire:submit="reverseReceipt" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Quantité à renverser') }}</flux:label>
                <flux:input wire:model="reverseQuantity" type="number" min="1" step="1" required />
                <flux:error name="reverseQuantity" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Raison (optionnel)') }}</flux:label>
                <flux:input wire:model="reverseReason" type="text" />
                <flux:error name="reverseReason" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showReverse', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="danger">{{ __('Renverser la réception') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Substitution d'un produit --}}
    <flux:modal wire:model="showSubstitute" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Substituer un produit') }}</flux:heading>
        @if ($substitutedLine)
            <flux:text class="text-zinc-500">
                {{ __(':product — :quantity à remplacer', ['product' => $substitutedLine->label, 'quantity' => $substitutedLine->quantity_outstanding]) }}
            </flux:text>
        @endif

        @if ($substituteProduct)
            <div class="mt-6 space-y-4">
                <flux:callout icon="arrows-right-left">
                    <flux:callout.text>
                        {{ __('Remplacer :old par :new (quantité : :quantity, coût : :cost $)?', [
                            'old' => $substitutedLine?->label,
                            'new' => $substituteProduct->display_name,
                            'quantity' => $substitutedLine?->quantity_outstanding,
                            'cost' => number_format($substituteProduct->cost, 2),
                        ]) }}
                    </flux:callout.text>
                </flux:callout>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="$set('substituteProductId', null)">{{ __('Changer de produit') }}</flux:button>
                    <flux:button type="button" variant="primary" wire:click="confirmSubstitute">{{ __('Confirmer la substitution') }}</flux:button>
                </div>
            </div>
        @else
            <div class="mt-6 space-y-3">
                <flux:input wire:model.live.debounce.300ms="substituteSearch" icon="magnifying-glass" :placeholder="__('Rechercher un produit du fournisseur…')" />

                <div class="max-h-72 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($substituteResults as $result)
                        <button type="button" wire:key="substitute-{{ $result->id }}" wire:click="selectSubstitute({{ $result->id }})"
                            class="flex w-full items-center justify-between px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span>{{ $result->display_name }}</span>
                            <span class="text-sm text-zinc-400">{{ number_format($result->cost, 2) }} $</span>
                        </button>
                    @empty
                        <div class="p-4 text-center">
                            <flux:text class="text-zinc-400">{{ __('Aucun produit trouvé.') }}</flux:text>
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end">
                    <flux:button type="button" variant="ghost" wire:click="$set('showSubstitute', false)">{{ __('Annuler') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Demande d'annulation d'une ligne --}}
    <livewire:supplier-line-cancellation-request />

    {{-- Réception --}}
    <flux:modal wire:model="showReceive" class="w-full max-w-2xl">
        <flux:heading class="mb-1">{{ __('Réceptionner la commande') }}</flux:heading>
        <flux:text class="text-zinc-500">{{ __('Saisissez les quantités reçues de chaque produit.') }}</flux:text>

        <form wire:submit="receive" class="mt-6 space-y-4">
            @foreach ($order->lines as $line)
                @continue($line->status->isClosed())
                <div class="flex items-start gap-3" wire:key="receipt-{{ $line->id }}">
                    <div class="flex-1 pt-2">
                        <flux:text class="font-medium">{{ $line->label }}</flux:text>
                        <flux:text class="text-xs text-zinc-400">
                            {{ __(':received / :ordered reçues', ['received' => $line->quantity_received, 'ordered' => $line->quantity]) }}
                        </flux:text>
                    </div>
                    <div class="w-24">
                        <flux:input wire:model="receipts.{{ $line->id }}.quantity" type="number" min="0" max="{{ $line->quantity_outstanding }}" step="1" :label="__('Reçu')" />
                        <flux:error name="receipts.{{ $line->id }}.quantity" />
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showReceive', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer la réception') }}</flux:button>
            </div>
        </form>
    </flux:modal>

</div>
