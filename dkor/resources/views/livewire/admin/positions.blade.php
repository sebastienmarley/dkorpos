<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Positions') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Titres d\'emploi des employés') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('Ajouter') }}
        </flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Position') }}</flux:table.column>
                <flux:table.column>{{ __('Employés') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($positions as $position)
                    <flux:table.row :key="$position->id">
                        <flux:table.cell variant="strong">{{ $position->name }}</flux:table.cell>
                        <flux:table.cell>{{ $position->users_count }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEdit({{ $position->id }})" />
                            @if ($position->users_count === 0)
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="delete({{ $position->id }})"
                                    wire:confirm="{{ __('Supprimer cette position ?') }}"
                                />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="briefcase" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucune position.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-sm">
        <flux:heading class="mb-1">{{ $editingId ? __('Modifier la position') : __('Nouvelle position') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="name" type="text" required autofocus />
                <flux:error name="name" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
