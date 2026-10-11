<div class="p-6">
    <div class="mb-6">
        <flux:link :href="route('accounting.invoices')" wire:navigate class="text-sm">← {{ __('Facturation fournisseurs') }}</flux:link>
        <div class="mt-1 flex items-center gap-3">
            <flux:heading level="1" size="xl">{{ $number }}</flux:heading>
            @if ($invoice)
                <flux:badge color="green" size="sm">{{ __('Facturée') }}</flux:badge>
            @else
                <flux:badge color="amber" size="sm">{{ __('À facturer') }}</flux:badge>
            @endif
        </div>
        <flux:text class="mt-1 text-zinc-500">
            <flux:link :href="route('suppliers.show', $supplier)" wire:navigate>{{ $supplier->name }}</flux:link>
            · {{ $isService ? __('Complétée le :date', ['date' => $documentDate->format('Y-m-d')]) : __('Reçue le :date', ['date' => $documentDate->format('Y-m-d')]) }}
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
                            <flux:table.cell variant="strong">{{ $invoiceLine->label }}</flux:table.cell>
                            <flux:table.cell align="end">
                                {{ $invoiceLine->quantity }}
                                @if ($invoiceLine->quantity_short > 0)
                                    <div class="text-xs text-red-600 dark:text-red-400">{{ __('dont :count manquant(s)', ['count' => $invoiceLine->quantity_short]) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $invoiceLine->unit_cost->format() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $invoiceLine->total->format() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="mt-6 max-w-xl rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt>{{ __('Facture n°') }}</dt><dd>{{ $invoice->invoice_number }} · {{ $invoice->invoice_date->format('Y-m-d') }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('Marchandises') }}</dt><dd>{{ $invoice->merchandise_total->format() }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('Transport') }}</dt><dd>{{ $invoice->freight_fee->format() }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('Douanes') }}</dt><dd>{{ $invoice->customs_fee->format() }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('Taxes') }}</dt><dd>{{ $invoice->taxes->format() }}</dd></div>
                <div class="flex justify-between font-medium"><dt>{{ __('Total calculé') }}</dt><dd>{{ $invoice->computed_total->format() }}</dd></div>
                <div class="flex justify-between font-medium"><dt>{{ __('Montant de la facture') }}</dt><dd>{{ $invoice->invoice_total->format() }}</dd></div>
                <div class="flex justify-between">
                    <dt>{{ __('Écart') }}</dt>
                    <dd>
                        @if ($invoice->hasVariance())
                            <flux:badge color="red" size="sm">{{ $invoice->variance->format() }}</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">{{ __('Aucun') }}</flux:badge>
                        @endif
                    </dd>
                </div>
                @if ($invoice->discount_due_date)
                    <div class="flex justify-between text-zinc-500">
                        <dt>{{ __('Escompte :percent % avant le :date', ['percent' => $invoice->discount_percent, 'date' => $invoice->discount_due_date->format('Y-m-d')]) }}</dt>
                        <dd>{{ $invoice->discount_amount->format() }}</dd>
                    </div>
                @endif
                @if ($invoice->credit_requested->isPositive())
                    <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-700">
                        <dt>{{ __('Demande de crédit (manquants)') }}</dt>
                        <dd>{{ $invoice->credit_requested->negate()->format() }}</dd>
                    </div>
                    <div class="flex justify-between font-medium"><dt>{{ __('Net après crédit') }}</dt><dd>{{ $invoice->netTotal()->format() }}</dd></div>
                    <div class="flex items-center justify-between">
                        <dt>{{ __('Note de crédit') }}</dt>
                        <dd>
                            @if ($invoice->isCreditPending())
                                <flux:badge color="amber" size="sm">{{ __('En attente') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">{{ __(':number reçue le :date', ['number' => $invoice->credit_note_number, 'date' => $invoice->credit_received_at->format('Y-m-d')]) }}</flux:badge>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($invoice->isCreditPending())
                @can('invoices.edit')
                    <form wire:submit="recordCreditNote" class="mt-4 flex flex-wrap items-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:field class="flex-1">
                            <flux:label>{{ __('N° de la note de crédit') }}</flux:label>
                            <flux:input wire:model="creditNoteNumber" type="text" />
                            <flux:error name="creditNoteNumber" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Reçue le') }}</flux:label>
                            <flux:input wire:model="creditReceivedAt" type="date" />
                            <flux:error name="creditReceivedAt" />
                        </flux:field>
                        <flux:button type="submit">{{ __('Inscrire la note de crédit') }}</flux:button>
                    </form>
                @endcan
            @endif
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
                        <flux:label>{{ $isService ? __('Date de complétion') : __('Date de réception') }}</flux:label>
                        <flux:input value="{{ $documentDate->format('Y-m-d') }}" type="date" disabled />
                    </flux:field>
                </div>

                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ $isService ? __('Description') : __('Produit') }}</flux:table.column>
                            <flux:table.column>{{ __('Commande') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Quantité') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Coût réel') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($lines as $line)
                                <flux:table.row :key="$line->id">
                                    <flux:table.cell variant="strong">{{ $line->label }}</flux:table.cell>
                                    <flux:table.cell>{{ $line instanceof \App\Models\ReceptionLine ? $line->orderLine->order->number : $line->order->number }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        {{ $line->billable_quantity }}
                                        @if ($line->quantity_short > 0)
                                            <div class="text-xs text-red-600 dark:text-red-400">{{ __('dont :count manquant(s)', ['count' => $line->quantity_short]) }}</div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end">
                                        <flux:input wire:model.live.debounce.400ms="unitCosts.{{ $line->id }}" type="number" min="0" step="0.01" class="w-28" />
                                        <flux:error name="unitCosts.{{ $line->id }}" />
                                    </flux:table.cell>
                                    <flux:table.cell align="end">{{ (isset($unitCosts[$line->id]) ? (\App\Support\Money::tryParse($unitCosts[$line->id]) ?? \App\Support\Money::zero()) : $line->unit_cost)->times($line->billable_quantity)->format() }}</flux:table.cell>
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
                        <div class="flex justify-between"><dt>{{ __('Marchandises (coûts réels)') }}</dt><dd>{{ $totals['merchandise_total']->format() }}</dd></div>
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
                        @if ($totals['credit_requested']->isPositive())
                            <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-700">
                                <dt>{{ __('Demande de crédit (manquants)') }}</dt>
                                <dd>{{ $totals['credit_requested']->negate()->format() }}</dd>
                            </div>
                            <div class="flex justify-between font-medium">
                                <dt>{{ __('Net après crédit') }}</dt>
                                <dd>{{ (\App\Support\Money::tryParse($invoiceTotal) ?? \App\Support\Money::zero())->minus($totals['credit_requested'])->format() }}</dd>
                            </div>
                            <flux:text class="pt-1 text-xs text-zinc-500">{{ __('Les manquants sont facturés par le fournisseur : la facture balance avec le total calculé, et le crédit demandé ramène le net à ce qui a été reçu.') }}</flux:text>
                        @endif
                    </dl>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button :href="route('accounting.invoices')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
                    <flux:button type="submit" variant="primary" icon="document-check">{{ __('Enregistrer la facture') }}</flux:button>
                </div>
            </form>
        @else
            <flux:text class="text-zinc-400">{{ $isService ? __('Cette commande n\'est pas encore facturée.') : __('Cette réception n\'est pas encore facturée.') }}</flux:text>
        @endcan
    @endif
</div>
