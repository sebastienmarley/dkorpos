<div class="p-6">
    <div class="mb-6">
        <flux:link :href="route('accounting.invoices')" wire:navigate class="text-sm">← {{ __('Facturation fournisseurs') }}</flux:link>
        <div class="mt-1 flex items-center gap-3">
            <flux:heading level="1" size="xl">{{ $invoice->invoice_number }}</flux:heading>
            <flux:badge color="green" size="sm">{{ __('Facturée') }}</flux:badge>
            <flux:badge color="zinc" size="sm">{{ __('Libre') }}</flux:badge>
        </div>
        <flux:text class="mt-1 text-zinc-500">
            <flux:link :href="route('suppliers.show', $invoice->supplier)" wire:navigate>{{ $invoice->supplier->name }}</flux:link>
            · {{ $invoice->invoice_date->format('Y-m-d') }}
            @if ($invoice->description)
                · {{ $invoice->description }}
            @endif
        </flux:text>
    </div>

    <div class="max-w-xl rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <dl class="space-y-1 text-sm">
            <div class="flex justify-between"><dt>{{ __('Montant avant frais et taxes') }}</dt><dd>{{ number_format($invoice->merchandise_total, 2) }} $</dd></div>
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
</div>
