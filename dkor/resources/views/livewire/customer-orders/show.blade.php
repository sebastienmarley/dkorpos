<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 grid grid-cols-1 items-center gap-4 md:grid-cols-[1fr_auto_1fr]">
        <div>
            <flux:link :href="route('customer-orders.index')" wire:navigate class="text-sm">
                ← {{ __('Commandes clients') }}
            </flux:link>
            <div class="mt-1 flex items-center gap-3">
                <flux:heading level="1" size="xl">{{ __('Commande #:id', ['id' => $order->id]) }}</flux:heading>
                <flux:badge :color="$order->status->color()" size="sm">{{ $order->status->label() }}</flux:badge>
            </div>
            <flux:text class="mt-1 text-zinc-500">
                {{ __('Créée le :date', ['date' => $order->created_at->format('Y-m-d')]) }}
                @if ($order->creator)
                    {{ __('par :name', ['name' => $order->creator->fullName()]) }}
                @endif
            </flux:text>
        </div>

        {{-- Client et vendeurs --}}
        <div class="flex flex-col items-center gap-2">
            <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:icon.user class="h-4 w-4 text-zinc-400" />
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $order->customer->firstname }} {{ $order->customer->lastname }}</span>
            </div>

            @if ($order->customer->credit_balance > 0)
                <flux:badge color="green" size="sm" icon="wallet">{{ __('Crédit au compte : :amount $', ['amount' => number_format($order->customer->credit_balance, 2)]) }}</flux:badge>
            @endif

            <div class="text-center text-sm text-zinc-600 dark:text-zinc-300">
                <span class="font-medium">{{ __('Vendeur(s)') }} :</span>
                {{ $order->salespeople->map(fn ($salesperson) => $salesperson->fullName())->join(', ') ?: '—' }}
            </div>
        </div>

        <div class="flex flex-col items-start gap-2 md:items-end">
            @can('customer_orders.edit')
                <flux:button variant="primary" icon="user-plus" wire:click="openCustomerModal">
                    {{ __('Changer le client') }}
                </flux:button>
            @endcan

            @can('customer_orders.assign_salespeople')
                <flux:button icon="users" wire:click="openSalespeopleModal">
                    {{ __('Ajouter un vendeur') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Produits --}}
    <div class="mb-6">
        @can('customer_orders.edit')
            <div class="mb-3 flex items-start gap-3">
                <flux:button icon="plus" wire:click="openProductModal">{{ __('Ajouter un produit') }}</flux:button>
                <flux:button icon="wrench" wire:click="openServiceModal">{{ __('Ajouter un service') }}</flux:button>

                <form wire:submit="scanUpc" class="w-64">
                    <flux:field>
                        <flux:input wire:model="upcScan" icon="qr-code" inputmode="numeric" autocomplete="off" autofocus :placeholder="__('Scanner un UPC…')" />
                        <flux:error name="upcScan" />
                    </flux:field>
                </form>

                @if ($order->balance_due > 0 || $order->lines->contains(fn ($line) => $line->status->isPickable() && $line->quantity_reserved > 0))
                    <flux:button variant="primary" icon="hand-raised" wire:click="openPickupModal" class="ms-auto">{{ __('Ramasser') }}</flux:button>
                @endif
            </div>
        @endcan

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Produit') }}</flux:table.column>
                    <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                    <flux:table.column>{{ __('Statut') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('En stock') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('En commande') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Qté totale') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Prix') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @php($canEditLines = auth()->user()->can('customer_orders.edit'))
                    @forelse ($order->lines as $line)
                        <flux:table.row
                            :key="$line->id"
                            x-on:click="{{ $canEditLines ? '$wire.openLineModal('.$line->id.')' : '' }}"
                            :class="$canEditLines ? 'cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800' : ''"
                        >
                            <flux:table.cell variant="strong">
                                @if ($line->isService())
                                    {{ $line->service->name }}
                                    <flux:badge size="sm" color="blue" class="ms-1">{{ __('Service') }}</flux:badge>
                                    <div class="max-w-xs truncate text-xs font-normal text-zinc-500">{{ $line->description }}</div>
                                @else
                                    {{ $line->product->model }}
                                    <span class="font-normal text-zinc-500">#{{ $line->product_id }}</span>
                                @endif
                                @unless ($line->is_taxable)
                                    <div class="text-xs font-normal text-zinc-400">{{ __('Non taxable') }}</div>
                                @endunless
                                @if ($line->note)
                                    <div class="max-w-xs truncate text-xs font-normal text-zinc-400">{{ $line->note }}</div>
                                @endif
                                @foreach ($line->defectiveProducts as $defective)
                                    <div class="text-xs font-normal text-amber-600 dark:text-amber-400">{{ __('Défectueux — dossier #:id · :resolution', ['id' => $defective->id, 'resolution' => $defective->resolution->label()]) }}</div>
                                @endforeach
                                @if ($line->cancellation_fee)
                                    <div class="text-xs font-normal text-red-600 dark:text-red-400">{{ __('Frais d\'annulation : :fee $', ['fee' => number_format($line->cancellation_fee, 2)]) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $line->isService() ? ($line->supplier?->name ?? __('Interne')) : $line->product->supplier->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$line->status->color()" size="sm">{{ $line->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $line->isService() ? '—' : $line->quantity_reserved }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $line->isService() ? '—' : $line->quantity_on_order }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $line->quantity }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($line->unit_price, 2) }} $</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($line->total, 2) }} $</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('customer_orders.edit')
                                    @if ($line->status->isEditable())
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click.stop="removeLine({{ $line->id }})" :label="__('Retirer')" />
                                    @endif
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9" class="py-8 text-center">
                                <flux:text class="text-zinc-400">{{ __('Aucun produit dans cette commande.') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="mt-4 grid gap-6 md:grid-cols-[1fr_18rem]">
            {{-- Paiements --}}
            <div>
                <flux:heading size="sm" class="mb-2">{{ __('Paiements') }}</flux:heading>
                @forelse ($order->payments as $payment)
                    <div wire:key="payment-{{ $payment->id }}" class="flex justify-between border-b border-zinc-100 py-1 text-sm dark:border-zinc-800">
                        <span class="text-zinc-600 dark:text-zinc-300">
                            {{ $payment->created_at->format('Y-m-d H:i') }} · {{ $payment->type === \App\Enums\CustomerPaymentType::Payment ? $payment->paymentMethod?->name : $payment->type->label().($payment->paymentMethod ? ' — '.$payment->paymentMethod->name : '') }}
                            @if ($payment->receiver)
                                <span class="text-zinc-400">· {{ $payment->receiver->fullName() }}</span>
                            @endif
                        </span>
                        <span @class(['text-red-600 dark:text-red-400' => $payment->amount < 0])>{{ number_format($payment->amount, 2) }} $</span>
                    </div>
                @empty
                    <flux:text class="text-sm text-zinc-400">{{ __('Aucun paiement.') }}</flux:text>
                @endforelse
            </div>

            {{-- Totaux --}}
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Sous-total') }}</dt><dd>{{ number_format($order->subtotal, 2) }} $</dd></div>
                @foreach ($order->taxLines as $taxLine)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">
                            {{ $taxLine->name }}
                            <span class="text-xs text-zinc-400">{{ rtrim(rtrim(number_format($taxLine->rate, 3, '.', ''), '0'), '.') }} %@if ($taxLine->registrationNumber()) · {{ $taxLine->registrationNumber() }}@endif</span>
                        </dt>
                        <dd>{{ number_format($taxLine->amount, 2) }} $</dd>
                    </div>
                @endforeach
                <div class="flex justify-between font-medium"><dt>{{ __('Total') }}</dt><dd>{{ number_format($order->total, 2) }} $</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Payé') }}</dt><dd>{{ number_format($order->amount_paid, 2) }} $</dd></div>
                <div class="flex justify-between border-t border-zinc-200 pt-1 font-semibold dark:border-zinc-700">
                    <dt>{{ $order->balance_due < 0 ? __('Crédit du client') : __('Solde à payer') }}</dt>
                    <dd>{{ number_format(abs($order->balance_due), 2) }} $</dd>
                </div>

                @if ($order->credit() > 0)
                    @can('customer_orders.edit')
                        <div class="flex flex-col gap-2 pt-2">
                            <flux:button size="sm" icon="banknotes" wire:click="openCreditRefundModal">{{ __('Rembourser le crédit') }}</flux:button>
                            <flux:button
                                size="sm"
                                icon="wallet"
                                wire:click="transferCreditToCustomer"
                                wire:confirm="{{ __('Porter :amount $ au compte du client ? La commande sera soldée.', ['amount' => number_format($order->credit(), 2)]) }}"
                            >
                                {{ __('Porter au compte du client') }}
                            </flux:button>
                        </div>
                    @endcan
                @endif
            </dl>
        </div>
    </div>

    @can('customer_orders.assign_salespeople')
        <flux:modal wire:model="showSalespeopleModal" class="w-full max-w-lg">
            <flux:heading class="mb-1">{{ __('Vendeurs') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Répartir la vente entre un maximum de 3 vendeurs. La somme doit être de 100 %.') }}</flux:text>

            <form wire:submit="saveSalespeople" class="mt-6 space-y-3">
                @foreach ($salespeopleDraft as $index => $row)
                    <div wire:key="draft-{{ $index }}" class="flex items-start gap-2">
                        <div class="flex-1">
                            <flux:select wire:model="salespeopleDraft.{{ $index }}.user_id">
                                <flux:select.option value="">{{ __('Sélectionner un employé…') }}</flux:select.option>
                                @foreach ($this->getEmployees() as $employee)
                                    <flux:select.option :value="$employee->id" :disabled="collect($salespeopleDraft)->except($index)->pluck('user_id')->contains($employee->id)">{{ $employee->fullName() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="salespeopleDraft.{{ $index }}.user_id" />
                        </div>

                        <div class="w-28">
                            <flux:select wire:model="salespeopleDraft.{{ $index }}.percent">
                                @foreach (collect(\App\Livewire\CustomerOrders\Show::PERCENT_OPTIONS)->when(! in_array((int) $row['percent'], \App\Livewire\CustomerOrders\Show::PERCENT_OPTIONS, true), fn ($options) => $options->push((int) $row['percent']))->sort() as $percent)
                                    <flux:select.option :value="$percent">{{ $percent }} %</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="salespeopleDraft.{{ $index }}.percent" />
                        </div>

                        @if (count($salespeopleDraft) > 1)
                            <flux:button type="button" variant="ghost" icon="x-mark" wire:click="removeSalesperson({{ $index }})" :label="__('Retirer')" />
                        @endif
                    </div>
                @endforeach

                <flux:error name="salespeopleDraft" />

                @if (count($salespeopleDraft) < \App\Livewire\CustomerOrders\Show::MAX_SALESPEOPLE)
                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addSalesperson">{{ __('Ajouter un vendeur') }}</flux:button>
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showSalespeopleModal', false)">{{ __('Annuler') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    {{-- Changement de client --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showCustomerModal" class="w-full max-w-lg">
            <flux:heading class="mb-1">{{ __('Changer le client') }}</flux:heading>

            <div class="mt-6">
                <x-customer-search :results="$customerResults" :search="$customerSearch" select="selectCustomer" />
            </div>
        </flux:modal>
    @endcan

    {{-- Recherche de produit --}}
    @can('customer_orders.edit')
        <flux:modal wire:model.live="showProductModal" class="w-full max-w-lg">
            <flux:heading class="mb-1">{{ __('Ajouter un produit') }}</flux:heading>

            @if (filled($pendingUpc))
                <flux:callout icon="qr-code" color="amber" class="mt-4">
                    <flux:callout.heading>{{ __('UPC :upc introuvable', ['upc' => $pendingUpc]) }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Cherchez le produit pour l\'ajouter à la commande.') }}</flux:callout.text>
                    @if ($this->canLinkPendingUpc())
                        <flux:checkbox wire:model="linkPendingUpc" :label="__('Associer cet UPC au produit choisi')" class="mt-2" />
                    @endif
                </flux:callout>
            @endif

            <div class="mt-6 space-y-4">
                <flux:field>
                    <flux:label>{{ __('Fournisseur') }}</flux:label>
                    <flux:select wire:model.live="productSupplierId">
                        <flux:select.option value="">{{ __('Tous — recherche par ID seulement') }}</flux:select.option>
                        @foreach ($this->getSuppliers() as $supplier)
                            <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ filled($productSupplierId) ? __('Produit') : __('ID du produit') }}</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="productSearch"
                        icon="magnifying-glass"
                        :placeholder="filled($productSupplierId) ? __('ID, modèle ou collection…') : __('ID du produit…')"
                    />

                    @if (filled($productSearch))
                        <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                            @forelse ($this->getProductResults() as $result)
                                <button type="button" wire:key="product-result-{{ $result->id }}" wire:click="addProduct({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <span class="min-w-0">
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $result->model }}</span>
                                        <span class="text-zinc-500"> · {{ $result->supplier->name }} · #{{ $result->id }}</span>
                                    </span>
                                </button>
                            @empty
                                <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun produit trouvé.') }}</div>
                            @endforelse
                        </div>
                    @endif
                </flux:field>

                @if (filled($productSupplierId))
                    <flux:field>
                        <flux:label>{{ __('Chercher dans les listes de prix') }}</flux:label>
                        <flux:input wire:model.live.debounce.300ms="priceListSearch" icon="magnifying-glass" :placeholder="__('Modèle ou collection…')" />

                        @if (filled($priceListSearch))
                            <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                                @forelse ($this->getPriceListResults() as $result)
                                    <button type="button" wire:key="price-list-{{ $result->id }}" wire:click="addFromPriceList({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                        <span class="min-w-0">
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $result->model }}</span>
                                            @if ($result->collection)
                                                <span class="text-zinc-500"> · {{ $result->collection }}</span>
                                            @endif
                                        </span>
                                        <span class="flex-shrink-0 text-zinc-500">{{ number_format($result->cost ?? 0, 2) }} $</span>
                                    </button>
                                @empty
                                    <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun résultat dans les listes de prix actives.') }}</div>
                                @endforelse
                            </div>
                        @endif
                    </flux:field>
                @endif
            </div>
        </flux:modal>
    @endcan

    {{-- Édition d'une ligne --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showLineModal" class="w-full max-w-lg">
            @if ($editingLine)
                @if ($editingLine->isService())
                    <flux:heading class="mb-1">{{ $editingLine->service->name }} <span class="font-normal text-zinc-500">· {{ $editingLine->supplier?->name ?? __('Interne') }}</span></flux:heading>
                @else
                    <flux:heading class="mb-1">{{ $editingLine->product->model }} <span class="font-normal text-zinc-500">#{{ $editingLine->product_id }}</span></flux:heading>
                @endif
                <flux:badge :color="$editingLine->status->color()" size="sm">{{ $editingLine->status->label() }}</flux:badge>

                <form wire:submit="saveLine" class="mt-6 space-y-4">
                    @php($editable = $editingLine->status->isEditable())

                    @if ($editingLine->isService())
                    <flux:field>
                        <flux:label>{{ __('Description') }}</flux:label>
                        <flux:input wire:model="editDescription" :disabled="! $editable" />
                        <flux:error name="editDescription" />
                    </flux:field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Quantité') }}</flux:label>
                            <flux:input wire:model="editQuantity" type="number" min="1" :disabled="! $editable" />
                            <flux:error name="editQuantity" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Prix vendant') }}</flux:label>
                            <flux:input wire:model="editUnitPrice" inputmode="decimal" :disabled="! $editable" />
                            <flux:error name="editUnitPrice" />
                        </flux:field>
                    </div>

                    @if (in_array($editingLine->status, [\App\Enums\CustomerOrderLineStatus::ToDo, \App\Enums\CustomerOrderLineStatus::Ordered], true))
                        <div class="rounded-lg border border-emerald-200 p-3 dark:border-emerald-900">
                            <flux:text class="mb-2 text-sm">{{ __('Le service a été rendu au client ?') }}</flux:text>
                            <flux:button type="button" size="sm" icon="check-circle" wire:click="completeService({{ $editingLine->id }})">{{ __('Marquer complété') }}</flux:button>
                        </div>
                    @endif
                    @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>
                                {{ __('En stock (réservé)') }}
                                <span class="ms-1 font-normal text-zinc-400">{{ __('max. :count', ['count' => $editingLine->quantity_reserved + ($editingLine->product->inventoryStock?->quantityAvailable() ?? 0)]) }}</span>
                            </flux:label>
                            <flux:input wire:model="editReserved" type="number" min="0" :disabled="! $editable" />
                            <flux:error name="editReserved" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('En commande') }}</flux:label>
                            <flux:input wire:model="editOnOrder" type="number" min="0" :disabled="! $editable" />
                            <flux:error name="editOnOrder" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Prix vendant') }}</flux:label>
                        <flux:input wire:model="editUnitPrice" inputmode="decimal" :disabled="! $editable" />
                        <flux:description>{{ __('Prix suggéré : :price $', ['price' => number_format($editingLine->product->selling_price, 2)]) }}</flux:description>
                        <flux:error name="editUnitPrice" />
                    </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>{{ __('Note') }}</flux:label>
                        <flux:textarea wire:model="editNote" rows="3" />
                        <flux:error name="editNote" />
                    </flux:field>

                    @if ($editingLine->status === \App\Enums\CustomerOrderLineStatus::Ordered && $editingLine->supplierOrderLine && $editingLine->quantity_on_order > 0)
                        <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <flux:text class="mb-2 text-sm">
                                {{ __(':count commandé(s) sur :number, déjà envoyée au fournisseur.', ['count' => $editingLine->quantity_on_order, 'number' => $editingLine->supplierOrderLine->order->number]) }}
                            </flux:text>
                            <flux:button
                                type="button"
                                size="sm"
                                icon="no-symbol"
                                x-on:click="$wire.set('showLineModal', false); $dispatch('open-supplier-line-cancellation', { lineId: {{ $editingLine->supplier_order_line_id }} })"
                            >
                                {{ __('Demander l\'annulation au fournisseur') }}
                            </flux:button>
                        </div>
                    @elseif ($editingLine->status === \App\Enums\CustomerOrderLineStatus::CancellationRequested)
                        <flux:callout icon="clock" color="amber" :text="__('Demande d\'annulation envoyée au fournisseur : en attente de sa réponse. Confirmée, la ligne est annulée sans frais.')" />
                    @endif

                    @if (in_array($editingLine->status, [\App\Enums\CustomerOrderLineStatus::Ordered, \App\Enums\CustomerOrderLineStatus::CancellationRequested], true) && $editingLine->quantity_on_order > 0)
                        @php($cancellationFee = $order->cancellationFeeFor($editingLine, $editingLine->quantity_on_order))
                        <div class="rounded-lg border border-red-200 p-3 dark:border-red-900">
                            <flux:text class="mb-2 text-sm">
                                {{ __('Le client ne veut pas attendre la réponse du fournisseur : annuler maintenant avec :percent % de frais (:fee $ avant taxes).', [
                                    'percent' => rtrim(rtrim(number_format($order->store->cancellation_fee_percent ?? 0, 2), '0'), '.'),
                                    'fee' => number_format($cancellationFee, 2),
                                ]) }}
                            </flux:text>
                            <flux:button
                                type="button"
                                size="sm"
                                variant="danger"
                                icon="x-circle"
                                wire:click="cancelLineWithFee({{ $editingLine->id }})"
                                wire:confirm="{{ __('Annuler :count article(s) avec :fee $ de frais ? La marchandise qui arrivera du fournisseur entrera en stock.', ['count' => $editingLine->quantity_on_order, 'fee' => number_format($cancellationFee, 2)]) }}"
                            >
                                {{ __('Annuler avec frais') }}
                            </flux:button>
                        </div>
                    @endif

                    @if (! $editingLine->isService() && $editingLine->status->isHandedOver())
                        <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <flux:text class="mb-2 text-sm font-medium">{{ __('Le client rapporte cet article ?') }}</flux:text>
                            <div class="flex gap-2">
                                <flux:button type="button" size="sm" icon="arrow-uturn-left" wire:click="openReturnModal({{ $editingLine->id }})">{{ __('Retour') }}</flux:button>
                                <flux:button type="button" size="sm" icon="wrench-screwdriver" wire:click="openDefectiveModal({{ $editingLine->id }})">{{ __('Défectueux') }}</flux:button>
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showLineModal', false)">{{ __('Annuler') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
                    </div>
                </form>
            @endif
        </flux:modal>
    @endcan

    {{-- Retour d'un article remis au client --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showReturnModal" class="w-full max-w-lg">
            @if ($returnLine)
                <flux:heading class="mb-1">{{ __('Retour') }} — {{ $returnLine->product->model }} <span class="font-normal text-zinc-500">#{{ $returnLine->product_id }}</span></flux:heading>
                <flux:text class="text-zinc-500">{{ __(':count remis au client à :price $', ['count' => $returnLine->quantity, 'price' => number_format($returnLine->unit_price, 2)]) }}</flux:text>

                @if ($returnStep === 'choice')
                    <form wire:submit="continueReturn" class="mt-6 space-y-4">
                        <flux:field>
                            <flux:label>{{ __('Quantité retournée') }}</flux:label>
                            <flux:input wire:model="returnQuantity" type="number" min="1" :max="$returnLine->quantity" />
                            <flux:error name="returnQuantity" />
                        </flux:field>

                        <flux:radio.group wire:model="returnType" :label="__('Le client veut')">
                            <flux:radio value="exchange" :label="__('Un échange')" :description="__('L\'article revient en stock; le montant payé reste au crédit de la commande pour le produit d\'échange.')" />
                            <flux:radio value="refund" :label="__('Un remboursement')" :description="__('L\'article revient en stock, puis le client est remboursé.')" />
                        </flux:radio.group>
                        <flux:error name="returnType" />

                        <div class="flex justify-end gap-3 pt-2">
                            <flux:button type="button" variant="ghost" wire:click="$set('showReturnModal', false)">{{ __('Annuler') }}</flux:button>
                            <flux:button type="submit" variant="primary">{{ $returnType === 'refund' ? __('Continuer vers le remboursement') : __('Reprendre l\'article') }}</flux:button>
                        </div>
                    </form>
                @else
                    <dl class="mt-4 space-y-1 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Quantité reprise') }}</dt><dd>{{ $returnQuantity }}</dd></div>
                        <div class="flex justify-between font-semibold"><dt>{{ __('Remboursable') }}</dt><dd>{{ number_format($returnRefundable, 2) }} $</dd></div>
                    </dl>
                    <flux:text class="mt-1 text-xs text-zinc-400">{{ __('Valeur retournée taxes incluses, sans descendre sous ce qu\'exige le reste de la commande.') }}</flux:text>

                    <form wire:submit="confirmReturnRefund" class="mt-6 space-y-3">
                        @foreach ($returnRefunds as $index => $refund)
                            <div wire:key="return-refund-{{ $index }}" class="flex flex-wrap items-start gap-2">
                                <div class="flex-1">
                                    <flux:select wire:model.live="returnRefunds.{{ $index }}.method_id">
                                        <flux:select.option value="">{{ __('Mode de remboursement…') }}</flux:select.option>
                                        @foreach ($this->getPaymentMethods() as $method)
                                            <flux:select.option :value="$method->id">{{ $method->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="returnRefunds.{{ $index }}.method_id" />
                                </div>
                                <div class="w-32">
                                    <flux:input wire:model.blur="returnRefunds.{{ $index }}.amount" inputmode="decimal" />
                                    <flux:error name="returnRefunds.{{ $index }}.amount" />
                                </div>
                                @if ((int) $refund['method_id'] === $this->cashMethodId())
                                    <flux:text class="w-full text-xs text-zinc-500">{{ __('À remettre en comptant (arrondi au 5 ¢) : :amount $', ['amount' => number_format($this->roundedCash($refund['amount']), 2)]) }}</flux:text>
                                @endif
                                @if (count($returnRefunds) > 1)
                                    <flux:button type="button" variant="ghost" icon="x-mark" wire:click="removeReturnRefund({{ $index }})" :label="__('Retirer')" />
                                @endif
                            </div>
                        @endforeach

                        <div class="flex items-center justify-between">
                            <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addReturnRefund">{{ __('Ajouter un mode') }}</flux:button>
                            <flux:text class="text-sm">{{ __('Total remboursé : :amount $', ['amount' => number_format($this->returnRefundsTotal(), 2)]) }}</flux:text>
                        </div>

                        <flux:error name="returnRefunds" />

                        <div class="flex justify-between gap-3 pt-2">
                            <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="backToReturnChoice">{{ __('Retour') }}</flux:button>
                            <flux:button type="submit" variant="primary">{{ __('Reprendre et rembourser') }}</flux:button>
                        </div>
                    </form>
                @endif
            @endif
        </flux:modal>
    @endcan

    {{-- Ajout d'un service --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showServiceModal" class="w-full max-w-lg">
            <flux:heading class="mb-1">{{ __('Ajouter un service') }}</flux:heading>

            <form wire:submit="addService" class="mt-6 space-y-4">
                <flux:field>
                    <flux:label>{{ __('Service') }}</flux:label>
                    <flux:select wire:model.live="serviceId">
                        <flux:select.option value="">{{ __('Choisir un service…') }}</flux:select.option>
                        @foreach ($this->getServices() as $service)
                            <flux:select.option :value="$service->id">{{ $service->name }}{{ $service->is_internal ? ' — '.__('interne') : '' }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="serviceId" />
                </flux:field>

                @if (filled($serviceId))
                    @php($offers = $this->getServiceOffers())
                    @if ($offers->isNotEmpty())
                        <flux:field>
                            <flux:label>{{ __('Fournisseur') }}</flux:label>
                            <flux:select wire:model.live="serviceSupplierId">
                                <flux:select.option value="">{{ __('Choisir un fournisseur…') }}</flux:select.option>
                                @foreach ($offers as $supplier)
                                    <flux:select.option :value="$supplier->id">{{ $supplier->name }} — {{ number_format((float) $supplier->pivot->selling_price, 2) }} $</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="serviceSupplierId" />
                        </flux:field>
                    @else
                        <flux:text class="text-sm text-zinc-500">{{ __('Service rendu par le magasin.') }}</flux:text>
                        <flux:error name="serviceSupplierId" />
                    @endif

                    @php($productLines = $order->lines->reject(fn ($line) => $line->isService()))
                    @if ($productLines->isNotEmpty())
                        <flux:field>
                            <flux:label>{{ __('Pour le produit (optionnel)') }}</flux:label>
                            <flux:select wire:model.live="serviceProductLineId">
                                <flux:select.option value="">{{ __('Aucun') }}</flux:select.option>
                                @foreach ($productLines as $productLine)
                                    <flux:select.option :value="$productLine->id">{{ $productLine->product->model }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>{{ __('Description') }}</flux:label>
                        <flux:input wire:model="serviceDescription" />
                        <flux:description>{{ __('Proposée par le service : précisez-la au besoin (étage, accès, particularités…).') }}</flux:description>
                        <flux:error name="serviceDescription" />
                    </flux:field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Quantité') }}</flux:label>
                            <flux:input wire:model="serviceQuantity" type="number" min="1" />
                            <flux:error name="serviceQuantity" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Prix vendant') }}</flux:label>
                            <flux:input wire:model="servicePrice" inputmode="decimal" />
                            <flux:error name="servicePrice" />
                        </flux:field>
                    </div>
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showServiceModal', false)">{{ __('Annuler') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Ajouter') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    {{-- Produit défectueux --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showDefectiveModal" class="w-full max-w-lg">
            @if ($defectiveLine)
                <flux:heading class="mb-1">{{ __('Défectueux') }} — {{ $defectiveLine->product->model }} <span class="font-normal text-zinc-500">#{{ $defectiveLine->product_id }}</span></flux:heading>
                <flux:text class="text-zinc-500">{{ __(':count remis au client à :price $', ['count' => $defectiveLine->quantity, 'price' => number_format($defectiveLine->unit_price, 2)]) }}</flux:text>

                @if ($defectiveStep === 'choice')
                    <form wire:submit="continueDefective" class="mt-6 space-y-4">
                        <flux:field>
                            <flux:label>{{ __('Quantité défectueuse') }}</flux:label>
                            <flux:input wire:model="defectiveQuantity" type="number" min="1" :max="$defectiveLine->quantity" />
                            <flux:error name="defectiveQuantity" />
                        </flux:field>

                        <flux:radio.group wire:model.live="defectivePath" :label="__('Solution')">
                            <flux:radio value="part" :label="__('Commander une pièce de remplacement')" :description="__('Le produit reste chez le client; la pièce est ajoutée sans frais à la commande de :supplier.', ['supplier' => $defectiveLine->product->supplier->name])" />
                            <flux:radio value="return" :label="__('Reprendre le produit')" :description="__('Le produit passe en inventaire défectueux; le client reçoit un remplacement ou un remboursement sans frais.')" />
                        </flux:radio.group>

                        @if ($defectivePath === 'part')
                            <flux:field>
                                <flux:label>{{ __('Pièce à commander') }}</flux:label>
                                <flux:input wire:model="defectivePart" :placeholder="__('No de pièce, description…')" />
                                <flux:error name="defectivePart" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Raison (optionnel)') }}</flux:label>
                                <flux:textarea wire:model="defectiveReason" rows="2" />
                                <flux:error name="defectiveReason" />
                            </flux:field>
                        @else
                            <flux:field>
                                <flux:label>{{ __('Raison du défaut') }}</flux:label>
                                <flux:textarea wire:model="defectiveReason" rows="3" />
                                <flux:error name="defectiveReason" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Photo') }}</flux:label>
                                <flux:input type="file" accept="image/*" disabled />
                                <flux:description>{{ __('Bientôt disponible.') }}</flux:description>
                            </flux:field>

                            <flux:radio.group wire:model.live="defectiveOutcome" :label="__('Le client veut')">
                                <flux:radio value="replace" :label="__('Un remplacement')" :description="__('Même produit au même prix, pris en stock ou commandé.')" />
                                <flux:radio value="refund" :label="__('Un remboursement sans frais')" />
                            </flux:radio.group>
                        @endif

                        <div class="flex justify-end gap-3 pt-2">
                            <flux:button type="button" variant="ghost" wire:click="$set('showDefectiveModal', false)">{{ __('Annuler') }}</flux:button>
                            <flux:button type="submit" variant="primary">
                                @if ($defectivePath === 'part')
                                    {{ __('Commander la pièce') }}
                                @elseif ($defectiveOutcome === 'refund')
                                    {{ __('Continuer vers le remboursement') }}
                                @else
                                    {{ __('Reprendre et remplacer') }}
                                @endif
                            </flux:button>
                        </div>
                    </form>
                @else
                    <dl class="mt-4 space-y-1 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Quantité reprise') }}</dt><dd>{{ $defectiveQuantity }}</dd></div>
                        <div class="flex justify-between font-semibold"><dt>{{ __('Remboursable') }}</dt><dd>{{ number_format($defectiveRefundable, 2) }} $</dd></div>
                    </dl>

                    <form wire:submit="confirmDefectiveRefund" class="mt-6 space-y-3">
                        @foreach ($defectiveRefunds as $index => $refund)
                            <div wire:key="defective-refund-{{ $index }}" class="flex flex-wrap items-start gap-2">
                                <div class="flex-1">
                                    <flux:select wire:model.live="defectiveRefunds.{{ $index }}.method_id">
                                        <flux:select.option value="">{{ __('Mode de remboursement…') }}</flux:select.option>
                                        @foreach ($this->getPaymentMethods() as $method)
                                            <flux:select.option :value="$method->id">{{ $method->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="defectiveRefunds.{{ $index }}.method_id" />
                                </div>
                                <div class="w-32">
                                    <flux:input wire:model.blur="defectiveRefunds.{{ $index }}.amount" inputmode="decimal" />
                                    <flux:error name="defectiveRefunds.{{ $index }}.amount" />
                                </div>
                                @if (count($defectiveRefunds) > 1)
                                    <flux:button type="button" variant="ghost" icon="x-mark" wire:click="removeDefectiveRefund({{ $index }})" :label="__('Retirer')" />
                                @endif
                                @if ((int) $refund['method_id'] === $this->cashMethodId())
                                    <flux:text class="w-full text-xs text-zinc-500">{{ __('À remettre en comptant (arrondi au 5 ¢) : :amount $', ['amount' => number_format($this->roundedCash($refund['amount']), 2)]) }}</flux:text>
                                @endif
                            </div>
                        @endforeach

                        <div class="flex items-center justify-between">
                            <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addDefectiveRefund">{{ __('Ajouter un mode') }}</flux:button>
                            <flux:text class="text-sm">{{ __('Total remboursé : :amount $', ['amount' => number_format($this->defectiveRefundsTotal(), 2)]) }}</flux:text>
                        </div>

                        <flux:error name="defectiveRefunds" />

                        <div class="flex justify-between gap-3 pt-2">
                            <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="backToDefectiveChoice">{{ __('Retour') }}</flux:button>
                            <flux:button type="submit" variant="primary">{{ __('Reprendre et rembourser') }}</flux:button>
                        </div>
                    </form>
                @endif
            @endif
        </flux:modal>
    @endcan

    {{-- Remboursement du crédit de la commande --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showCreditRefundModal" class="w-full max-w-lg">
            <flux:heading class="mb-1">{{ __('Rembourser le crédit') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Crédit de la commande : :amount $', ['amount' => number_format($order->credit(), 2)]) }}</flux:text>

            <form wire:submit="refundCredit" class="mt-6 space-y-3">
                @foreach ($creditRefunds as $index => $refund)
                    <div wire:key="credit-refund-{{ $index }}" class="flex flex-wrap items-start gap-2">
                        <div class="flex-1">
                            <flux:select wire:model.live="creditRefunds.{{ $index }}.method_id">
                                <flux:select.option value="">{{ __('Mode de remboursement…') }}</flux:select.option>
                                @foreach ($this->getPaymentMethods() as $method)
                                    <flux:select.option :value="$method->id">{{ $method->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="creditRefunds.{{ $index }}.method_id" />
                        </div>
                        <div class="w-32">
                            <flux:input wire:model.blur="creditRefunds.{{ $index }}.amount" inputmode="decimal" />
                            <flux:error name="creditRefunds.{{ $index }}.amount" />
                        </div>
                                @if ((int) $refund['method_id'] === $this->cashMethodId())
                                    <flux:text class="w-full text-xs text-zinc-500">{{ __('À remettre en comptant (arrondi au 5 ¢) : :amount $', ['amount' => number_format($this->roundedCash($refund['amount']), 2)]) }}</flux:text>
                                @endif
                        @if (count($creditRefunds) > 1)
                            <flux:button type="button" variant="ghost" icon="x-mark" wire:click="removeCreditRefund({{ $index }})" :label="__('Retirer')" />
                        @endif
                    </div>
                @endforeach

                <div class="flex items-center justify-between">
                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addCreditRefund">{{ __('Ajouter un mode') }}</flux:button>
                    <flux:text class="text-sm">{{ __('Total remboursé : :amount $', ['amount' => number_format($this->creditRefundsTotal(), 2)]) }}</flux:text>
                </div>

                <flux:error name="creditRefunds" />

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showCreditRefundModal', false)">{{ __('Annuler') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Rembourser') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    {{-- Ramassage --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showPickupModal" class="w-full max-w-2xl">
            <flux:heading class="mb-1">{{ __('Ramassage') }}</flux:heading>

            @if ($pickupStep === 'items')
                <flux:text class="text-zinc-500">{{ __('Quantités remises au client (stock réservé seulement). Laissez à 0 pour encaisser un paiement sans ramassage.') }}</flux:text>

                @if ($pickupQuantities === [])
                    <flux:text class="mt-4 text-sm text-zinc-400">{{ __('Aucun article disponible à ramasser : vous pouvez encaisser un paiement.') }}</flux:text>
                @endif

                <form wire:submit="continueToPayment" class="mt-6 space-y-3">
                    @foreach ($order->lines->filter(fn ($line) => isset($pickupQuantities[$line->id])) as $line)
                        <div wire:key="pickup-{{ $line->id }}" class="flex items-start justify-between gap-3">
                            <div class="min-w-0 text-sm">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $line->product->model }} <span class="font-normal text-zinc-500">#{{ $line->product_id }}</span></div>
                                <div class="text-xs text-zinc-400">
                                    {{ __('Disponible : :count', ['count' => $line->quantity_reserved]) }} · {{ number_format($line->unit_price, 2) }} $
                                    @if ($line->quantity_on_order > 0)
                                        · {{ __(':count encore en commande', ['count' => $line->quantity_on_order]) }}
                                    @endif
                                </div>
                            </div>
                            <div class="w-24">
                                <flux:input wire:model="pickupQuantities.{{ $line->id }}" type="number" min="0" :max="$line->quantity_reserved" size="sm" />
                                <flux:error name="pickupQuantities.{{ $line->id }}" />
                            </div>
                        </div>
                    @endforeach

                    <flux:error name="pickupQuantities" />

                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showPickupModal', false)">{{ __('Annuler') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Continuer vers le paiement') }}</flux:button>
                    </div>
                </form>
            @else
                <dl class="mt-4 space-y-1 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Total de la commande') }}</dt><dd>{{ number_format($order->total, 2) }} $</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Déjà payé') }}</dt><dd>{{ number_format($order->amount_paid, 2) }} $</dd></div>
                    <div class="flex justify-between font-semibold"><dt>{{ __('À encaisser maintenant') }}</dt><dd>{{ number_format($pickupRequired, 2) }} $</dd></div>
                </dl>
                <flux:text class="mt-1 text-xs text-zinc-400">
                    {{ __('Articles remis payés à 100 %, dépôt de :percent % sur le reste, taxes incluses.', ['percent' => rtrim(rtrim(number_format(config('sales.deposit_percent'), 2), '0'), '.')]) }}
                </flux:text>

                <form wire:submit="confirmPickup" class="mt-6 space-y-3">
                    @if ($order->customer->credit_balance > 0)
                        <div class="flex items-start gap-2">
                            <div class="flex flex-1 items-center gap-2 text-sm">
                                <flux:icon.wallet class="h-4 w-4 text-green-600" />
                                <span>{{ __('Crédit client') }}</span>
                                <span class="text-zinc-400">{{ __('(disponible : :amount $)', ['amount' => number_format($order->customer->credit_balance, 2)]) }}</span>
                            </div>
                            <div class="w-32">
                                <flux:input wire:model.blur="pickupCredit" inputmode="decimal" />
                                <flux:error name="pickupCredit" />
                            </div>
                            @if (count($pickupPayments) > 1)
                                <div class="w-10"></div>
                            @endif
                        </div>
                    @endif

                    @foreach ($pickupPayments as $index => $payment)
                        <div wire:key="pickup-payment-{{ $index }}" class="flex flex-wrap items-start gap-2">
                            <div class="flex-1">
                                <flux:select wire:model.live="pickupPayments.{{ $index }}.method_id">
                                    <flux:select.option value="">{{ __('Mode de paiement…') }}</flux:select.option>
                                    @foreach ($this->getPaymentMethods() as $method)
                                        <flux:select.option :value="$method->id">{{ $method->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="pickupPayments.{{ $index }}.method_id" />
                            </div>
                            <div class="w-32">
                                <flux:input wire:model.blur="pickupPayments.{{ $index }}.amount" inputmode="decimal" />
                                <flux:error name="pickupPayments.{{ $index }}.amount" />
                            </div>
                            @if (count($pickupPayments) > 1)
                                <flux:button type="button" variant="ghost" icon="x-mark" wire:click="removePickupPayment({{ $index }})" :label="__('Retirer')" />
                            @endif

                            @if ((int) $payment['method_id'] === $this->cashMethodId())
                                <div class="flex w-full flex-wrap items-center gap-x-4 gap-y-2 rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800">
                                    <span>{{ __('À percevoir (arrondi au 5 ¢) : :amount $', ['amount' => number_format($this->roundedCash($payment['amount']), 2)]) }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-zinc-500">{{ __('Reçu') }}</span>
                                        <div class="w-28">
                                            <flux:input wire:model.live.debounce.400ms="pickupPayments.{{ $index }}.tendered" inputmode="decimal" size="sm" />
                                        </div>
                                    </div>
                                    <span class="font-semibold">{{ __('Monnaie à rendre : :amount $', ['amount' => number_format($this->changeDue($index), 2)]) }}</span>
                                    <flux:error name="pickupPayments.{{ $index }}.tendered" />
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="flex items-center justify-between">
                        <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addPickupPayment">{{ __('Ajouter un mode de paiement') }}</flux:button>
                        <flux:text class="text-sm">{{ __('Total reçu : :amount $', ['amount' => number_format($this->pickupPaymentsTotal(), 2)]) }}</flux:text>
                    </div>

                    <flux:error name="pickupPayments" />

                    <div class="flex justify-between gap-3 pt-2">
                        <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="backToPickupItems">{{ __('Retour') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Confirmer le ramassage') }}</flux:button>
                    </div>
                </form>
            @endif
        </flux:modal>
    @endcan

    <livewire:supplier-line-cancellation-request />

    <livewire:customer-form />
</div>
