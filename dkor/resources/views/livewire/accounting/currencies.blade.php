<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Devises') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Gestion des devises') }}</flux:text>
        </div>
        @can('currencies.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Ajouter') }}
            </flux:button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Taux') }}</flux:table.column>
                <flux:table.column>{{ __('Fournisseurs') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($currencies as $currency)
                    <flux:table.row :key="$currency->id" @class(['opacity-50' => $currency->is_archived])>
                        <flux:table.cell variant="strong" class="font-mono">{{ $currency->code }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $currency->name }}
                            @if ($currency->is_archived)
                                <flux:badge size="sm" class="ms-2">{{ __('Archivée') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ rtrim(rtrim(number_format($currency->rate, 6, '.', ''), '0'), '.') }}</flux:table.cell>
                        <flux:table.cell>{{ $currency->suppliers_count }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            @can('currencies.edit')
                                <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEdit({{ $currency->id }})" />
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    :icon="$currency->is_archived ? 'arrow-uturn-left' : 'archive-box'"
                                    :title="$currency->is_archived ? __('Restaurer') : __('Archiver')"
                                    wire:click="toggleArchive({{ $currency->id }})"
                                />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="currency-dollar" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucune devise.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-sm">
        <flux:heading class="mb-1">{{ $editingId ? __('Modifier la devise') : __('Nouvelle devise') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Code (ISO 4217)') }}</flux:label>
                <flux:input wire:model="code" type="text" placeholder="CAD" maxlength="3" class="font-mono uppercase" required autofocus />
                <flux:error name="code" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="name" type="text" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Taux') }}</flux:label>
                <flux:input wire:model="rate" type="number" step="0.000001" min="0" required />
                <flux:error name="rate" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
