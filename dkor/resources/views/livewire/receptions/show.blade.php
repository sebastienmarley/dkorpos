<div class="p-6">
    <div class="mb-6">
        <flux:link :href="route('receptions.index')" wire:navigate class="text-sm">← {{ __('Réceptions') }}</flux:link>
        <flux:heading level="1" size="xl" class="mt-1">{{ $reception->number }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            <flux:link :href="route('suppliers.show', $reception->supplier)" wire:navigate>{{ $reception->supplier->name }}</flux:link>
            · {{ $reception->received_at->format('Y-m-d H:i') }}
            @if ($reception->receiver)
                · {{ __('par :name', ['name' => $reception->receiver->firstname.' '.$reception->receiver->lastname]) }}
            @endif
            @if ($reception->reference)
                · {{ __('Bordereau') }} {{ $reception->reference }}
            @endif
        </flux:text>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Commande') }}</flux:table.column>
                <flux:table.column>{{ __('Produit') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Quantité reçue') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Renversée') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Coût unitaire') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($reception->lines as $line)
                    <flux:table.row :key="$line->id">
                        <flux:table.cell>
                            <flux:link :href="route('supplier-orders.show', $line->orderLine->order)" wire:navigate>{{ $line->orderLine->order->number }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">{{ $line->orderLine->label }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $line->quantity }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $line->quantity_reversed ?: '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($line->unit_cost, 2) }} $</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($line->total, 2) }} $</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-3 text-end">
        <flux:text class="text-lg font-semibold">{{ __('Total') }} : {{ number_format($reception->total, 2) }} $</flux:text>
    </div>

    @if ($reception->notes)
        <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Notes') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-line">{{ $reception->notes }}</flux:text>
        </div>
    @endif
</div>
