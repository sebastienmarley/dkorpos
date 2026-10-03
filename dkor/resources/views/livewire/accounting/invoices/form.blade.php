<div class="p-6">
    <div class="mb-6">
        <flux:link :href="route('accounting.invoices')" wire:navigate class="text-sm">← {{ __('Facturation fournisseurs') }}</flux:link>
        <div class="mt-1 flex items-center gap-3">
            <flux:heading level="1" size="xl">{{ $reception->number }}</flux:heading>
            @if ($invoice)
                <flux:badge color="green" size="sm">{{ __('Facturée') }}</flux:badge>
            @else
                <flux:badge color="amber" size="sm">{{ __('À facturer') }}</flux:badge>
            @endif
        </div>
        <flux:text class="mt-1 text-zinc-500">
            <flux:link :href="route('suppliers.show', $reception->supplier)" wire:navigate>{{ $reception->supplier->name }}</flux:link>
            · {{ __('Reçue le :date', ['date' => ($reception->completed_at ?? $reception->received_at)->format('Y-m-d')]) }}
            ·
            @foreach ($orders as $order)
                <flux:link :href="route('supplier-orders.show', $order)" wire:navigate>{{ $order->number }}</flux:link>{{ $order->quote_number ? ' (quote # '.$order->quote_number.')' : '' }}{{ ! $loop->last ? ', ' : '' }}
            @endforeach
        </flux:text>
    </div>

    @if ($invoice)
        {{-- Facture enregistrée --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Produit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Quantité') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Coût réel') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($invoice->lines as $invoiceLine)
                        <flux:table.row :key="$invoiceLine->id">
                            <flux:table.cell variant="strong">{{ $invoiceLine->receptionLine->orderLine->label }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $invoiceLine->quantity }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($invoiceLine->unit_cost, 2) }} $</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($invoiceLine->total, 2) }} $</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="mt-6 max-w-xl rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt>{{ __('Facture n°') }}</dt><dd>{{ $invoice->invoice_number }} · {{ $invoice->invoice_date->format('Y-m-d') }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('Marchandises') }}</dt><dd>{{ number_format($invoice->merchandise_total, 2) }} $</dd></div>
                <div class="flex justify-between"><dt>{{ __('Transport') }}</dt><dd>{{ number_format($invoice->freight_fee, 2) }} $</dd></div>
                <div class="flex justify-between"><dt>{{ __('Douanes') }}</dt><dd>{{ number_format($invoice->customs_fee, 2) }} $</dd></div>
                <div class="flex justify-between"><dt>{{ __('Taxes') }}</dt><dd>{{ number_format($invoice->taxes, 2) }} $</dd></div>
                <div class="flex justify-between font-medium"><dt>{{ __('Total calculé') }}</dt><dd>{{ number_format($invoice->computed_total, 2) }} $</dd></div>
                <div class="flex justify-between font-medium"><dt>{{ __('Montant de la facture') }}</dt><dd>{{ number_format($invoice->invoice_total, 2) }} $</dd></div>
                <div class="flex justify-between">
                    <dt>{{ __('Écart') }}</dt>
                    <dd>
                        @if ($invoice->hasVariance())
                            <flux:badge color="red" size="sm">{{ number_format($invoice->variance, 2) }} $</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">{{ __('Aucun') }}</flux:badge>
                        @endif
                    </dd>
                </div>
                @if ($invoice->discount_due_date)
                    <div class="flex justify-between text-zinc-500">
                        <dt>{{ __('Escompte :percent % avant le :date', ['percent' => $invoice->discount_percent, 'date' => $invoice->discount_due_date->format('Y-m-d')]) }}</dt>
                        <dd>{{ number_format($invoice->discount_amount, 2) }} $</dd>
                    </div>
                @endif
            </dl>
        </div>
    @else
        @can('invoices.create')
            <form wire:submit="save" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-3">
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
                    <flux:field>
                        <flux:label>{{ __('Date de réception') }}</flux:label>
                        <flux:input value="{{ ($reception->completed_at ?? $reception->received_at)->format('Y-m-d') }}" type="date" disabled />
                    </flux:field>
                </div>

                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Produit') }}</flux:table.column>
                            <flux:table.column>{{ __('Commande') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Quantité') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Coût réel') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($lines as $line)
                                <flux:table.row :key="$line->id">
                                    <flux:table.cell variant="strong">{{ $line->orderLine->label }}</flux:table.cell>
                                    <flux:table.cell>{{ $line->orderLine->order->number }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $line->quantity_net }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        <flux:input wire:model.live.debounce.400ms="unitCosts.{{ $line->id }}" type="number" min="0" step="0.01" class="w-28" />
                                        <flux:error name="unitCosts.{{ $line->id }}" />
                                    </flux:table.cell>
                                    <flux:table.cell align="end">{{ number_format($line->quantity_net * (float) ($unitCosts[$line->id] ?? $line->unit_cost), 2) }} $</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                <div class="grid gap-4 sm:grid-cols-4">
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
                    <flux:field>
                        <flux:label>{{ __('Taxes') }}</flux:label>
                        <flux:input wire:model.live.debounce.400ms="taxes" type="number" min="0" step="0.01" required />
                        <flux:error name="taxes" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Montant de la facture') }}</flux:label>
                        <flux:input wire:model.live.debounce.400ms="invoiceTotal" type="number" min="0" step="0.01" required />
                        <flux:error name="invoiceTotal" />
                    </flux:field>
                </div>

                {{-- Comparaison --}}
                <div class="max-w-xl rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt>{{ __('Marchandises (coûts réels)') }}</dt><dd>{{ number_format($totals['merchandise_total'], 2) }} $</dd></div>
                        <div class="flex justify-between"><dt>{{ __('Transport + douanes + taxes') }}</dt><dd>{{ number_format((float) $freightFee + (float) $customsFee + (float) $taxes, 2) }} $</dd></div>
                        <div class="flex justify-between font-medium"><dt>{{ __('Total calculé') }}</dt><dd>{{ number_format($totals['computed_total'], 2) }} $</dd></div>
                        <div class="flex justify-between font-medium"><dt>{{ __('Montant de la facture') }}</dt><dd>{{ number_format((float) $invoiceTotal, 2) }} $</dd></div>
                        <div class="flex items-center justify-between">
                            <dt>{{ __('Écart') }}</dt>
                            <dd>
                                @if (filled($invoiceTotal) && abs($totals['variance']) >= 0.005)
                                    <flux:badge color="red" size="sm">{{ number_format($totals['variance'], 2) }} $</flux:badge>
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
                                <dd>{{ number_format($totals['discount_amount'], 2) }} $</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button :href="route('accounting.invoices')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
                    <flux:button type="submit" variant="primary" icon="document-check">{{ __('Enregistrer la facture') }}</flux:button>
                </div>
            </form>
        @else
            <flux:text class="text-zinc-400">{{ __('Cette réception n\'est pas encore facturée.') }}</flux:text>
        @endcan
    @endif
</div>
