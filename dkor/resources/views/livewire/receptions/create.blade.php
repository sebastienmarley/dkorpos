<div class="p-6">
    <div class="mb-6">
        <flux:link :href="route('receptions.index')" wire:navigate class="text-sm">← {{ __('Réceptions') }}</flux:link>
        <flux:heading level="1" size="xl" class="mt-1">{{ __('Nouvelle réception') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Choisissez un fournisseur, cochez les lignes reçues et saisissez les quantités.') }}</flux:text>
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
                <flux:label>{{ __('N° de bordereau') }}</flux:label>
                <flux:input wire:model="reference" type="text" />
                <flux:error name="reference" />
            </flux:field>

            @if (filled($supplierId))
                <flux:field>
                    <flux:label>{{ __('Filtrer les commandes') }}</flux:label>
                    <flux:input wire:model.live.debounce.300ms="orderSearch" icon="magnifying-glass" :placeholder="__('N° de commande ou quote #')" />
                </flux:field>
            @endif
        </div>

        @if (filled($supplierId))
            @forelse ($orders as $order)
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900" wire:key="order-{{ $order->id }}">
                    <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <div>
                            <flux:link :href="route('supplier-orders.show', $order)" wire:navigate class="font-medium">{{ $order->number }}</flux:link>
                            @if ($order->quote_number)
                                <flux:text class="text-sm text-zinc-500">{{ __('Quote #') }} {{ $order->quote_number }}</flux:text>
                            @endif
                        </div>
                        <flux:badge :color="$order->status->color()" size="sm">{{ $order->status->label($order->type) }}</flux:badge>
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column />
                            <flux:table.column>{{ __('Produit') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Restant') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Reçu') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Coût réel') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($order->lines as $line)
                                <flux:table.row :key="$line->id">
                                    <flux:table.cell>
                                        <flux:checkbox wire:model.live="selected.{{ $line->id }}" />
                                    </flux:table.cell>
                                    <flux:table.cell variant="strong">
                                        {{ $line->label }}
                                        @if ($line->status === \App\Enums\SupplierOrderLineStatus::CancellationRequested)
                                            <flux:badge :color="$line->status->color()" size="sm" class="ms-2">{{ $line->status->label() }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end">{{ $line->quantity_outstanding }} / {{ $line->quantity }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        @if ($selected[$line->id] ?? false)
                                            <flux:input wire:model="quantities.{{ $line->id }}" type="number" min="0" max="{{ $line->quantity_outstanding }}" step="1" class="w-24" />
                                            <flux:error name="quantities.{{ $line->id }}" />
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end">
                                        @if ($selected[$line->id] ?? false)
                                            <flux:input wire:model="costs.{{ $line->id }}" type="number" min="0" step="0.01" class="w-28" />
                                            <flux:error name="costs.{{ $line->id }}" />
                                        @else
                                            {{ number_format($line->unit_cost, 2) }} $
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                    <flux:text class="text-zinc-400">{{ __('Aucune commande de produits en attente de réception pour ce fournisseur.') }}</flux:text>
                </div>
            @endforelse

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" rows="3" />
                <flux:error name="notes" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:button :href="route('receptions.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="inbox-arrow-down">{{ __('Enregistrer la réception') }}</flux:button>
            </div>
        @endif
    </form>
</div>
