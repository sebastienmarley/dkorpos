<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Zones d\'inventaire') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Zones de l\'entrepôt où le stock se compte, chacune par deux compteurs. Elles servent d\'une prise à l\'autre.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('Nouvelle zone') }}</flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Ordre') }}</flux:table.column>
                <flux:table.column>{{ __('Zone') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($zones as $zone)
                    <flux:table.row :key="$zone->id">
                        <flux:table.cell>{{ $zone->sort_order }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $zone->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$zone->is_active ? 'green' : 'zinc'">{{ $zone->is_active ? __('Active') : __('Désactivée') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $zone->id }})">{{ __('Modifier') }}</flux:button>
                            <flux:button size="xs" variant="ghost" wire:click="toggleActive({{ $zone->id }})">{{ $zone->is_active ? __('Désactiver') : __('Réactiver') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-12 text-center">
                            <flux:text class="text-zinc-400">{{ __('Aucune zone : sans zone, le stock se compte à la main dans la prise.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ $editingId ? __('Modifier la zone') : __('Nouvelle zone') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Nom')" :placeholder="__('Allée 1, Mezzanine…')" required />
            <flux:input wire:model="sortOrder" type="number" min="0" :label="__('Ordre')" required />

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
