<div class="sm:p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <flux:heading level="1" size="xl">{{ __('Clients') }}</flux:heading>
            <flux:tooltip :content="__('Liste de tous les clients')">
                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
            </flux:tooltip>
        </div>

        @can('customers.create')
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-customer-create')">
                {{ __('Ajouter un client') }}
            </flux:button>
        @endcan
    </div>

    {{-- Recherche --}}
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher par nom, courriel ou téléphone…') }}" icon="magnifying-glass" />
    </div>

    {{-- Résultats : rien avant une recherche, la table seulement s'il y a des clients --}}
    @if ($customers->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Nom') }}</flux:table.column>
                    <flux:table.column class="max-sm:hidden">{{ __('Courriel') }}</flux:table.column>
                    <flux:table.column class="max-sm:hidden">{{ __('Téléphone') }}</flux:table.column>
                    <flux:table.column>{{ __('Cellulaire') }}</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @php($canEditCustomers = auth()->user()->can('customers.edit'))
                    @foreach ($customers as $customer)
                        <x-table.row-link :key="$customer->id" :action="$canEditCustomers ? '$dispatch(\'open-customer-edit\', { id: '.$customer->id.' })' : null">
                            <flux:table.cell variant="strong">
                                @can('customers.edit')
                                    <button
                                        type="button"
                                        class="cursor-pointer text-left hover:underline"
                                        x-on:click="$dispatch('open-customer-edit', { id: {{ $customer->id }} })"
                                    >
                                        {{ $customer->firstname }} {{ $customer->lastname }}
                                    </button>
                                @else
                                    {{ $customer->firstname }} {{ $customer->lastname }}
                                @endcan
                            </flux:table.cell>

                            <flux:table.cell class="max-sm:hidden">{{ $customer->email ?: '—' }}</flux:table.cell>

                            <flux:table.cell class="max-sm:hidden">{{ $customer->phone ?: '—' }}</flux:table.cell>

                            <flux:table.cell>{{ $customer->cellphone ?: '—' }}</flux:table.cell>
                        </x-table.row-link>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <flux:text class="mt-3 text-sm text-zinc-400">
            {{ trans_choice(':count client|:count clients', $customers->count()) }}
        </flux:text>
    @elseif (filled($search))
        <div class="flex flex-col items-center gap-2 py-12 text-center">
            <flux:icon name="users" class="h-8 w-8 text-zinc-300" />
            <flux:text class="text-zinc-400">{{ __('Aucun client trouvé pour cette recherche.') }}</flux:text>
        </div>
    @endif

    <livewire:customer-form />
</div>
