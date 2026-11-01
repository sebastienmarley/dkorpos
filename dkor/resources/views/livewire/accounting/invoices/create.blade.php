<div class="sm:p-6">
    <div class="mb-6">
        <flux:link :href="route('accounting.invoices')" wire:navigate class="text-sm">← {{ __('Facturation fournisseurs') }}</flux:link>
        <flux:heading level="1" size="xl" class="mt-1">{{ __('Nouvelle facture') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Facture rattachée à un fournisseur, sans lien avec une réception ni une commande.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:field>
                <flux:label>{{ __('Fournisseur') }}</flux:label>
                <flux:select wire:model.live="supplierId" :placeholder="__('Choisir un fournisseur…')">
                    @foreach ($suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="supplierId" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Numéro de facture') }}</flux:label>
                <flux:input wire:model="invoiceNumber" type="text" required />
                <flux:error name="invoiceNumber" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Date de facturation') }}</flux:label>
                <flux:input wire:model.live="invoiceDate" type="date" required />
                <flux:error name="invoiceDate" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Description') }}</flux:label>
            <flux:input wire:model="description" type="text" />
            <flux:error name="description" />
        </flux:field>

        @if ($this->isCarrier())
            @php($shipments = $this->getInvoiceableShipments())
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="sm" class="mb-2">{{ __('Envois couverts par la facture') }}</flux:heading>
                @forelse ($shipments as $shipment)
                    <div wire:key="invoice-shipment-{{ $shipment->id }}" class="flex items-center justify-between gap-3 border-b border-zinc-100 py-1 text-sm last:border-0 dark:border-zinc-800">
                        <flux:checkbox wire:model.live="shipmentIds.{{ $shipment->id }}" :label="__(':date — commande #:order (:customer) — suivi :tracking', ['date' => $shipment->shipped_at->format('Y-m-d'), 'order' => $shipment->customer_order_id, 'customer' => $shipment->order->customer->lastname, 'tracking' => $shipment->tracking_number])" />
                        <span class="shrink-0 text-zinc-500">{{ $shipment->shipping_cost?->format() }}</span>
                    </div>
                @empty
                    <flux:text class="text-sm text-zinc-400">{{ __('Aucun envoi de ce transporteur à facturer.') }}</flux:text>
                @endforelse
                <flux:error name="shipmentIds" />
                <div class="mt-2 flex justify-end text-sm text-zinc-500">
                    {{ __('Coûts soumis des envois cochés : :amount', ['amount' => $this->selectedShipmentsCost()->format()]) }}
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <flux:field>
                    <flux:label>{{ __('Montant avant taxes') }}</flux:label>
                    <flux:input wire:model.live.debounce.400ms="merchandiseTotal" type="number" min="0" step="0.01" required />
                    <flux:error name="merchandiseTotal" />
                </flux:field>
                @foreach ($taxAmounts as $taxName => $amount)
                    <flux:field wire:key="tax-{{ $taxName }}">
                        <flux:label>{{ $taxName }}</flux:label>
                        <flux:input wire:model.live.debounce.400ms="taxAmounts.{{ $taxName }}" type="number" min="0" step="0.01" required />
                        <flux:error name="taxAmounts.{{ $taxName }}" />
                    </flux:field>
                @endforeach
                <flux:field>
                    <flux:label>{{ __('Montant de la facture') }}</flux:label>
                    <flux:input wire:model.live.debounce.400ms="invoiceTotal" type="number" min="0" step="0.01" required />
                    <flux:error name="invoiceTotal" />
                </flux:field>
            </div>

            @php($shipmentsVariance = (\App\Support\Money::tryParse($merchandiseTotal) ?? \App\Support\Money::zero())->minus($this->selectedShipmentsCost()))
            <div class="flex items-center justify-between max-w-xl text-sm">
                <span>{{ __('Écart avec les coûts soumis (avant taxes)') }}</span>
                @if ($shipmentsVariance->isZero())
                    <flux:badge color="green" size="sm">{{ __('Aucun') }}</flux:badge>
                @else
                    <flux:badge color="amber" size="sm">{{ $shipmentsVariance->format() }}</flux:badge>
                @endif
            </div>
            <flux:text class="text-xs text-zinc-400">{{ __('À l\'enregistrement : le montant avant taxes va au compte « Frais d\'expédition et transport », chaque taxe à son compte « à recevoir », et le total aux comptes fournisseurs.') }}</flux:text>
        @else
        <div class="grid gap-4 sm:grid-cols-5">
            <flux:field>
                <flux:label>{{ __('Montant avant frais et taxes') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="merchandiseTotal" type="number" min="0" step="0.01" required />
                <flux:error name="merchandiseTotal" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Frais de transport') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="freightFee" type="number" min="0" step="0.01" required />
                <flux:error name="freightFee" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Frais de douanes') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="customsFee" type="number" min="0" step="0.01" required />
                <flux:error name="customsFee" />
            </flux:field>
            @foreach ($taxAmounts as $taxName => $amount)
                <flux:field wire:key="tax-{{ $taxName }}">
                    <flux:label>{{ $taxName }}</flux:label>
                    <flux:input wire:model.live.debounce.400ms="taxAmounts.{{ $taxName }}" type="number" min="0" step="0.01" required />
                    <flux:error name="taxAmounts.{{ $taxName }}" />
                </flux:field>
            @endforeach
            <flux:field>
                <flux:label>{{ __('Montant de la facture') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="invoiceTotal" type="number" min="0" step="0.01" required />
                <flux:error name="invoiceTotal" />
            </flux:field>
        </div>

        <flux:field class="max-w-xl">
            <flux:label>{{ __('Compte de charge') }}</flux:label>
            <flux:select wire:model="ledgerAccountId" :placeholder="__('Choisir un compte…')">
                @foreach ($expenseAccounts as $account)
                    <flux:select.option :value="$account->id">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:description>{{ __('Le montant avant taxes va à ce compte, chaque taxe à son compte « à recevoir », et le total aux comptes fournisseurs.') }}</flux:description>
            <flux:error name="ledgerAccountId" />
        </flux:field>
        @endif

        @if ($totals)
            <div class="max-w-xl rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt>{{ __('Montant avant frais et taxes') }}</dt><dd>{{ $totals['merchandise_total']->format() }}</dd></div>
                    <div class="flex justify-between"><dt>{{ __('Transport + douanes + taxes') }}</dt><dd>{{ $totals['computed_total']->minus($totals['merchandise_total'])->format() }}</dd></div>
                    <div class="flex justify-between font-medium"><dt>{{ __('Total calculé') }}</dt><dd>{{ $totals['computed_total']->format() }}</dd></div>
                    <div class="flex justify-between font-medium"><dt>{{ __('Montant de la facture') }}</dt><dd>{{ (\App\Support\Money::tryParse($invoiceTotal) ?? \App\Support\Money::zero())->format() }}</dd></div>
                    <div class="flex items-center justify-between">
                        <dt>{{ __('Écart') }}</dt>
                        <dd>
                            @if (filled($invoiceTotal) && ! $totals['variance']->isZero())
                                <flux:badge color="red" size="sm">{{ $totals['variance']->format() }}</flux:badge>
                            @elseif (filled($invoiceTotal))
                                <flux:badge color="green" size="sm">{{ __('Aucun') }}</flux:badge>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    @if ($totals['discount_due_date'])
                        <div class="flex justify-between text-zinc-500">
                            <dt>{{ __('Escompte :percent % si payé avant le :date', ['percent' => $totals['discount_percent'], 'date' => $totals['discount_due_date']->format('Y-m-d')]) }}</dt>
                            <dd>{{ $totals['discount_amount']->format() }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <flux:button :href="route('accounting.invoices')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="document-check">{{ __('Enregistrer la facture') }}</flux:button>
        </div>
    </form>
</div>
