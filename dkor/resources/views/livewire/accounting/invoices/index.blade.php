<div class="p-6">
    <div class="mb-6">
        <flux:heading level="1" size="xl">{{ __('Facturation fournisseurs') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Cherchez une réception par son numéro, un bon de commande ou un quote #, puis ouvrez sa facturation.') }}</flux:text>
    </div>

    <div class="mb-4 flex items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" autocomplete="off" class="flex-1" placeholder="{{ __('N° de réception (RC-…), bon de commande (CF-…) ou quote #') }}" />
        <flux:checkbox wire:model.live="includeInvoiced" :label="__('Inclure les facturées')" />
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$receptions">
            <flux:table.columns>
                <flux:table.column>{{ __('Réception') }}</flux:table.column>
                <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                <flux:table.column>{{ __('Bons de commande') }}</flux:table.column>
                <flux:table.column>{{ __('Quote #') }}</flux:table.column>
                <flux:table.column>{{ __('Reçue le') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Unités') }}</flux:table.column>
                <flux:table.column>{{ __('Facturation') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($receptions as $reception)
                    @php
                        $orders = $reception->invoiceableLines->map(fn ($line) => $line->orderLine->order)->unique('id');
                    @endphp
                    <flux:table.row :key="$reception->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('accounting.invoices.reception', $reception)" wire:navigate>{{ $reception->number }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $reception->supplier->name }}</flux:table.cell>
                        <flux:table.cell>{{ $orders->pluck('number')->join(', ') }}</flux:table.cell>
                        <flux:table.cell>{{ $orders->pluck('quote_number')->filter()->join(', ') ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ ($reception->completed_at ?? $reception->received_at)->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $reception->invoiceableLines->sum('quantity_net') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($reception->invoice)
                                <flux:badge color="green" size="sm">{{ __('Facturée') }} · {{ $reception->invoice->invoice_number }}</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">{{ __('À facturer') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="document-text" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">
                                    {{ filled($search) ? __('Aucune réception trouvée pour cette recherche.') : __('Aucune réception à facturer.') }}
                                </flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-3">
        <flux:text class="text-sm text-zinc-400">{{ trans_choice(':count réception|:count réceptions', $receptions->total()) }}</flux:text>
    </div>
</div>
