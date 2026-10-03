<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Commandes fournisseurs') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Commandes de produits et de services') }}</flux:text>
        </div>

        @can('supplier_orders.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Nouvelle commande') }}
            </flux:button>
        @endcan
    </div>

    {{-- Filtres --}}
    <div class="mb-4 flex gap-3">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher par numéro, quote # ou fournisseur…') }}" icon="magnifying-glass" />
        </div>

        <div class="w-44">
            <flux:select wire:model.live="typeFilter">
                <flux:select.option value="">{{ __('Tous les types') }}</flux:select.option>
                @foreach ($this->getOrderTypes() as $type)
                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-52">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="">{{ __('Tous les statuts') }}</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$orders">
            <flux:table.columns>
                <flux:table.column>{{ __('Numéro') }}</flux:table.column>
                <flux:table.column>{{ __('Quote #') }}</flux:table.column>
                <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Créée le') }}</flux:table.column>
                <flux:table.column>{{ __('Envoyée le') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($orders as $order)
                    <flux:table.row :key="$order->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('supplier-orders.show', $order)" wire:navigate>
                                {{ $order->number }}
                            </flux:link>
                        </flux:table.cell>

                        <flux:table.cell>{{ $order->quote_number ?: '—' }}</flux:table.cell>

                        <flux:table.cell>{{ $order->supplier->name }}</flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :color="$order->type->color()" size="sm">{{ $order->type->label() }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :color="$order->status->color()" size="sm">{{ $order->status->label($order->type) }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>{{ number_format($order->total, 2) }} $</flux:table.cell>

                        <flux:table.cell>{{ $order->created_at->format('Y-m-d') }}</flux:table.cell>

                        <flux:table.cell>{{ $order->sent_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="truck" class="h-8 w-8 text-zinc-300" />
                                @if (filled($search) || filled($typeFilter) || filled($statusFilter))
                                    <flux:text class="text-zinc-400">{{ __('Aucune commande trouvée pour ces critères.') }}</flux:text>
                                @else
                                    <flux:text class="text-zinc-400">{{ __('Aucune commande. Cliquez sur « Nouvelle commande » pour commencer.') }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-3">
        <flux:text class="text-sm text-zinc-400">
            {{ trans_choice(':count commande|:count commandes', $orders->total()) }}
        </flux:text>
    </div>

    {{-- Création --}}
    <flux:modal wire:model="showCreate" class="w-full max-w-md">
        <form wire:submit="create" class="space-y-6">
            <flux:heading size="lg">{{ __('Nouvelle commande fournisseur') }}</flux:heading>

            <flux:select wire:model.live="newType" :label="__('Type de commande')">
                @foreach ($this->getOrderTypes() as $type)
                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="newSupplierId" :label="__('Fournisseur')" :placeholder="__('Choisir un fournisseur…')">
                @foreach ($creatableSuppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCreate', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Créer la commande') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
