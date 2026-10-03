<div class="p-6">
    <div class="mb-6 flex items-start justify-between gap-4">
      <div>
        <flux:heading level="1" size="xl">{{ __('Facturation fournisseurs') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Cherchez une réception de produits ou une commande de services par son numéro, son bon de commande ou son quote #, puis ouvrez sa facturation.') }}</flux:text>
      </div>

        @can('invoices.create')
            <flux:button variant="primary" icon="plus" :href="route('accounting.invoices.create')" wire:navigate>{{ __('Nouvelle facture') }}</flux:button>
        @endcan
    </div>

    <div class="mb-4 flex items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" autocomplete="off" class="flex-1" placeholder="{{ __('N° de réception (RC-…), bon de commande (CF-…) ou quote #') }}" />
        <flux:checkbox wire:model.live="includeInvoiced" :label="__('Inclure les facturées')" />
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$rows">
            <flux:table.columns>
                <flux:table.column>{{ __('Document') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                <flux:table.column>{{ __('Bons de commande') }}</flux:table.column>
                <flux:table.column>{{ __('Quote #') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Unités') }}</flux:table.column>
                <flux:table.column>{{ __('Facturation') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row :key="$row['key']">
                        <flux:table.cell variant="strong">
                            <flux:link :href="$row['url']" wire:navigate>{{ $row['number'] }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['kind'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['supplier'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['orders'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['quotes'] ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $row['date']->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $row['units'] ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($row['invoice'])
                                <flux:badge color="green" size="sm">{{ __('Facturée') }} · {{ $row['invoice']->invoice_number }}</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">{{ __('À facturer') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="document-text" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">
                                    {{ filled($search) ? __('Rien à facturer ne correspond à cette recherche.') : __('Rien à facturer.') }}
                                </flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-3">
        <flux:text class="text-sm text-zinc-400">{{ trans_choice(':count document|:count documents', $rows->total()) }}</flux:text>
    </div>
</div>
