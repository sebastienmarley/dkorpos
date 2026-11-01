<div class="sm:p-6">
    <div class="mb-6">
        <flux:heading level="1" size="xl">{{ __('Comptes à payer') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Factures fournisseur qui ont un solde dû, par fournisseur. Un paiement se répartit sur une ou plusieurs factures; il s\'annule, mais ne se supprime pas.') }}</flux:text>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
        <div class="w-full sm:w-72">
            <flux:select wire:model.live="supplierFilter">
                <flux:select.option value="">{{ __('Tous les fournisseurs') }}</flux:select.option>
                @foreach ($suppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <flux:text class="font-medium">{{ __('Total dû (CAD) : :total', ['total' => $totalCad->format()]) }}</flux:text>
    </div>

    @forelse ($invoicesBySupplier as $supplierInvoices)
        @php($supplier = $supplierInvoices->first()->supplier)
        <div wire:key="supplier-{{ $supplier->id }}" class="mb-6 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ $supplier->name }} <span class="text-sm font-normal text-zinc-500">· {{ $supplier->currency?->code ?? 'CAD' }}</span></flux:heading>
                @can('supplier_payments.create')
                    <flux:button size="sm" variant="primary" icon="banknotes" wire:click="openPayment({{ $supplier->id }})">{{ __('Payer') }}</flux:button>
                @endcan
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Facture') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Escompte') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Montant net') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Solde dû') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($supplierInvoices as $invoice)
                        <flux:table.row :key="'invoice-'.$invoice->id">
                            <flux:table.cell variant="strong">{{ $invoice->invoice_number }}</flux:table.cell>
                            <flux:table.cell>{{ $invoice->invoice_date->format('Y-m-d') }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($invoice->discountAvailableOn(today()))
                                    @if ($invoice->discount_due_date->lte($warningDate))
                                        <flux:badge color="amber" size="sm">{{ __(':amount d\'ici le :date', ['amount' => $invoice->discount_amount->format(), 'date' => $invoice->discount_due_date->format('Y-m-d')]) }}</flux:badge>
                                    @else
                                        <flux:text class="text-sm">{{ __(':amount d\'ici le :date', ['amount' => $invoice->discount_amount->format(), 'date' => $invoice->discount_due_date->format('Y-m-d')]) }}</flux:text>
                                    @endif
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $invoice->netTotal()->format() }}</flux:table.cell>
                            <flux:table.cell align="end" variant="strong">{{ $invoice->balanceDue()->format() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @empty
        <div class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 bg-white py-12 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:icon name="check-circle" class="h-8 w-8 text-zinc-300" />
            <flux:text class="text-zinc-400">{{ __('Aucune facture à payer.') }}</flux:text>
        </div>
    @endforelse

    <flux:heading size="lg" class="mb-3 mt-10">{{ __('Paiements récents') }}</flux:heading>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                <flux:table.column>{{ __('Mode') }}</flux:table.column>
                <flux:table.column>{{ __('Factures') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Montant') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Déboursé (CAD)') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($payments as $payment)
                    <flux:table.row :key="'payment-'.$payment->id">
                        <flux:table.cell>{{ $payment->paid_on->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->supplier->name }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $payment->method->label() }}{{ $payment->reference ? ' · '.$payment->reference : '' }}
                            <flux:text class="text-xs text-zinc-400">{{ $payment->account->code }} — {{ $payment->account->name }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ $payment->allocations->map(fn ($allocation) => $allocation->invoice->invoice_number)->join(', ') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $payment->amount->format() }} {{ $payment->supplier->currency?->code ?? 'CAD' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $payment->amount_cad->format() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($payment->isCancelled())
                                <flux:tooltip :content="$payment->cancellation_reason">
                                    <flux:badge color="red" size="sm">{{ __('Annulé') }}</flux:badge>
                                </flux:tooltip>
                            @else
                                @can('supplier_payments.create')
                                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="openCancel({{ $payment->id }})">{{ __('Annuler') }}</flux:button>
                                @endcan
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center">
                            <flux:text class="text-zinc-400">{{ __('Aucun paiement.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showPayment" class="w-full max-w-3xl">
        <form wire:submit="savePayment" class="space-y-4">
            <flux:heading>{{ __('Paiement à :supplier', ['supplier' => $paymentSupplier?->name]) }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.live="paidOn" type="date" :label="__('Date du paiement')" required />
                <flux:select wire:model="method" :label="__('Mode')">
                    @foreach ($methods as $paymentMethod)
                        <flux:select.option :value="$paymentMethod->value">{{ $paymentMethod->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="reference" :label="__('Référence (n° de chèque, de virement…)')" />
                <flux:select wire:model="cashAccountId" :label="__('Compte payeur')">
                    @foreach ($cashAccounts as $account)
                        <flux:select.option :value="$account->id">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Facture') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Solde dû') }}</flux:table.column>
                    <flux:table.column>{{ __('Montant payé') }}</flux:table.column>
                    <flux:table.column>{{ __('Escompte') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($paymentInvoices as $invoice)
                        <flux:table.row :key="'pay-'.$invoice->id">
                            <flux:table.cell>
                                {{ $invoice->invoice_number }}
                                <flux:text class="text-xs text-zinc-400">{{ $invoice->invoice_date->format('Y-m-d') }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="ghost" wire:click="payInFull({{ $invoice->id }})">{{ $invoice->balanceDue()->format() }}</flux:button>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:input wire:model="amounts.{{ $invoice->id }}" type="number" min="0" step="0.01" size="sm" class="w-32" />
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($invoice->discount_amount->isPositive() && $invoice->discount_due_date)
                                    <flux:checkbox wire:model="discounts.{{ $invoice->id }}" :label="__(':amount (d\'ici le :date)', ['amount' => $invoice->discount_amount->format(), 'date' => $invoice->discount_due_date->format('Y-m-d')])" />
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            <flux:text class="text-xs text-zinc-400">{{ __('Cliquez sur un solde dû pour le payer au complet, escompte compris s\'il s\'applique encore.') }}</flux:text>
            <flux:error name="amounts" />

            @if ($paymentSupplier && $paymentSupplier->conversionRate() !== '1')
                <flux:field class="max-w-xs">
                    <flux:label>{{ __('Montant déboursé (CAD)') }}</flux:label>
                    <flux:input wire:model="amountCad" type="number" min="0" step="0.01" required />
                    <flux:description>{{ __('Ce qui a réellement quitté le compte. L\'écart avec le taux des factures va au gain ou perte de change.') }}</flux:description>
                    <flux:error name="amountCad" />
                </flux:field>
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showPayment', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer le paiement') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCancel" class="w-full max-w-md">
        <form wire:submit="cancelPayment" class="space-y-4">
            <flux:heading>{{ __('Annuler le paiement') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Les factures redeviennent dues et l\'écriture du paiement est renversée.') }}</flux:text>
            <flux:textarea wire:model="cancellationReason" :label="__('Raison')" rows="2" required />

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancel', false)">{{ __('Retour') }}</flux:button>
                <flux:button type="submit" variant="danger">{{ __('Annuler le paiement') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
