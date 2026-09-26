<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Clients') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Liste de tous les clients') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-customer-create')">
            {{ __('Ajouter un client') }}
        </flux:button>
    </div>

    {{-- Recherche --}}
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher un client…') }}" icon="magnifying-glass" />
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Courriel') }}</flux:table.column>
                <flux:table.column>{{ __('Téléphone') }}</flux:table.column>
                <flux:table.column>{{ __('Cellulaire') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @if (blank($search))
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="magnifying-glass" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Entrez un nom, courriel ou numéro pour rechercher un client.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @else
                    @forelse ($customers as $customer)
                        <flux:table.row :key="$customer->id">
                            <flux:table.cell variant="strong">
                                <div class="flex items-center gap-3">
                                    <flux:avatar
                                        :name="$customer->firstname.' '.$customer->lastname"
                                        size="sm"
                                    />
                                    {{ $customer->firstname }} {{ $customer->lastname }}
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>{{ $customer->email ?: '—' }}</flux:table.cell>

                            <flux:table.cell>{{ $customer->phone ?: '—' }}</flux:table.cell>

                            <flux:table.cell>{{ $customer->cellphone ?: '—' }}</flux:table.cell>

                            <flux:table.cell align="end">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    x-on:click="$dispatch('open-customer-edit', { id: {{ $customer->id }} })"
                                >
                                    {{ __('Modifier') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon name="users" class="h-8 w-8 text-zinc-300" />
                                    <flux:text class="text-zinc-400">{{ __('Aucun client trouvé pour cette recherche.') }}</flux:text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                @endif
            </flux:table.rows>
        </flux:table>
    </div>

    @if (filled($search) && $customers->isNotEmpty())
        <flux:text class="mt-3 text-sm text-zinc-400">
            {{ trans_choice(':count client|:count clients', $customers->count()) }}
        </flux:text>
    @endif

    <livewire:customer-form />
</div>
