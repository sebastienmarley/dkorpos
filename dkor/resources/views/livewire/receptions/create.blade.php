<div class="p-6">
    <div class="mb-6">
        <flux:link :href="route('receptions.index')" wire:navigate class="text-sm">← {{ __('Réceptions') }}</flux:link>
        <flux:heading level="1" size="xl" class="mt-1">{{ __('Nouvelle réception') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Choisissez un fournisseur, cherchez un bon de commande, cochez les lignes reçues avec leurs quantités, puis commencez la réception.') }}</flux:text>
    </div>

    <form wire:submit="start" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Fournisseur') }}</flux:label>
                <flux:select wire:model.live="supplierId" :placeholder="__('Choisir un fournisseur…')">
                    @foreach ($suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="supplierId" />
            </flux:field>

            @if (filled($supplierId))
                <flux:field>
                    <flux:label>{{ __('N° de bon de commande') }}</flux:label>
                    <flux:input wire:model.live.debounce.300ms="orderSearch" icon="magnifying-glass" autocomplete="off" :placeholder="__('Ex.: CF-000123 ou quote #')" />
                </flux:field>
            @endif
        </div>

        @if (filled($supplierId))
            @if (filled(trim($orderSearch)))
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
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                        <flux:text class="text-zinc-400">{{ __('Aucun bon de commande à recevoir ne correspond à cette recherche.') }}</flux:text>
                    </div>
                @endforelse
            @else
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                    <flux:text class="text-zinc-400">{{ __('Saisissez un numéro de bon de commande (ou un quote #) pour afficher ses lignes.') }}</flux:text>
                </div>
            @endif

            {{-- Sélection courante, toutes recherches confondues --}}
            @if ($selectedLines->isNotEmpty())
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('À recevoir') }} ({{ $selectedLines->count() }})</flux:heading>
                    <div class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($selectedLines as $line)
                            <div class="flex items-center justify-between gap-3 py-2" wire:key="selected-{{ $line->id }}">
                                <div>
                                    <flux:text class="font-medium">{{ $line->label }}</flux:text>
                                    <flux:text class="text-xs text-zinc-400">{{ $line->order->number }}</flux:text>
                                </div>
                                <div class="flex items-center gap-3">
                                    <flux:text>× {{ $quantities[$line->id] ?? 0 }}</flux:text>
                                    <flux:button type="button" size="xs" variant="ghost" icon="x-mark" wire:click="removeSelected({{ $line->id }})" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button :href="route('receptions.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="inbox-arrow-down" :disabled="$selectedLines->isEmpty()">{{ __('Commencer la réception') }}</flux:button>
            </div>
        @endif
    </form>
</div>
