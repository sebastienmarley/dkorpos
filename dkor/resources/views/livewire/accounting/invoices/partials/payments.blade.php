{{-- Statut de paiement d'une facture et ses paiements (#132). Attend $invoice, avec paymentAllocations.payment. --}}
<div class="mt-6 max-w-xl rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
    <div class="mb-2 flex items-center justify-between">
        <flux:heading size="sm">{{ __('Paiements') }}</flux:heading>
        @if ($invoice->isPaid())
            <flux:badge color="green" size="sm">{{ __('Payée') }}</flux:badge>
        @else
            <flux:badge color="amber" size="sm">{{ __('Solde dû : :amount', ['amount' => $invoice->balanceDue()->format()]) }}</flux:badge>
        @endif
    </div>
    <dl class="space-y-1 text-sm">
        @forelse ($invoice->paymentAllocations as $allocation)
            <div wire:key="invoice-payment-{{ $allocation->id }}" @class(['flex justify-between', 'text-zinc-400 line-through' => $allocation->payment->isCancelled()])>
                <dt>{{ $allocation->payment->paid_on->format('Y-m-d') }} · {{ $allocation->payment->method->label() }}{{ $allocation->payment->reference ? ' '.$allocation->payment->reference : '' }}</dt>
                <dd>
                    {{ $allocation->amount->format() }}
                    @if ($allocation->discount->isPositive())
                        <span class="text-zinc-500">{{ __('+ escompte :amount', ['amount' => $allocation->discount->format()]) }}</span>
                    @endif
                </dd>
            </div>
        @empty
            <flux:text class="text-zinc-400">{{ __('Aucun paiement.') }}</flux:text>
        @endforelse
    </dl>
</div>
