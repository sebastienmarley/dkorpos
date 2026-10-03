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
                    · {{ $order->last_emailed_at
                        ? __('Dernier courriel le :date', ['date' => $order->last_emailed_at->format('Y-m-d H:i')])
                        : __('Aucun courriel envoyé') }}
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
                    <flux:button variant="primary" icon="clock" wire:click="markPending" wire:confirm="{{ __('Mettre cette commande en attente d\'envoi? Elle ne sera plus un brouillon.') }}">
                        {{ __('Mettre en attente') }}
                    </flux:button>
                @endif

                @if ($status === Status::Pending)
                    <flux:button variant="primary" icon="paper-airplane" wire:click="send" wire:confirm="{{ __('Envoyer cette commande au fournisseur?') }}">
                        {{ __('Envoyer') }}
                    </flux:button>
                @endif

                @if ($status->isOpen())
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

                @if ($status === Status::Received)
                    <flux:button variant="primary" icon="document-text" wire:click="openInvoice">
                        {{ __('Saisir la facture') }}
                    </flux:button>
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
                                            <flux:button size="xs" variant="ghost" icon="no-symbol" wire:click="openCancelRequest({{ $line->id }})" />
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
                            <flux:select wire:model.live="productId" :placeholder="__('Choisir un produit…')">
                                @foreach ($products as $product)
                                    <flux:select.option :value="$product->id">{{ $product->display_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="productId" />
                        @else
                            <flux:input wire:model="description" :placeholder="__('Description du service')" />
                            <flux:error name="description" />
                        @endif
                    </div>
                    <div class="w-28">
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
    @if ($order->invoice_number)
        <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Facture du fournisseur') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('N° :number du :date — :total $', [
                    'number' => $order->invoice_number,
                    'date' => $order->invoice_date->format('Y-m-d'),
                    'total' => number_format($order->invoice_total, 2),
                ]) }}
            </flux:text>
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
    <flux:modal wire:model="showCancelRequest" class="w-full max-w-md">
        <flux:heading class="mb-1">{{ __('Demander l\'annulation de la ligne') }}</flux:heading>
        <flux:text class="text-zinc-500">{{ __('Un courriel est envoyé au fournisseur; la ligne reste « en demande d\'annulation » jusqu\'à sa réponse.') }}</flux:text>

        <form wire:submit="requestLineCancellation" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Raison (optionnel)') }}</flux:label>
                <flux:input wire:model="cancelReason" type="text" />
                <flux:error name="cancelReason" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelRequest', false)">{{ __('Retour') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Envoyer la demande') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Réception --}}
    <flux:modal wire:model="showReceive" class="w-full max-w-2xl">
        <flux:heading class="mb-1">{{ __('Réceptionner la commande') }}</flux:heading>
        <flux:text class="text-zinc-500">{{ __('Saisissez les quantités reçues et le coût réel de chaque produit.') }}</flux:text>

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
                    <div class="w-32">
                        <flux:input wire:model="receipts.{{ $line->id }}.unit_cost" type="number" min="0" step="0.01" :label="__('Coût réel')" />
                        <flux:error name="receipts.{{ $line->id }}.unit_cost" />
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showReceive', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer la réception') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Facture --}}
    <flux:modal wire:model="showInvoice" class="w-full max-w-md">
        <flux:heading class="mb-1">{{ __('Facture du fournisseur') }}</flux:heading>

        <form wire:submit="saveInvoice" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Numéro de facture') }}</flux:label>
                <flux:input wire:model="invoiceNumber" type="text" required />
                <flux:error name="invoiceNumber" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Date de la facture') }}</flux:label>
                <flux:input wire:model="invoiceDate" type="date" required />
                <flux:error name="invoiceDate" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Total facturé') }}</flux:label>
                <flux:input wire:model="invoiceTotal" type="number" min="0" step="0.01" required />
                <flux:error name="invoiceTotal" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showInvoice', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
