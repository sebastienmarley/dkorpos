<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Couleurs') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Gestion des couleurs produits') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('Ajouter') }}
        </flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Couleur') }}</flux:table.column>
                <flux:table.column>{{ __('Code hex') }}</flux:table.column>
                <flux:table.column>{{ __('Produits') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($colors as $color)
                    <flux:table.row :key="$color->id">
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-2">
                                @if ($color->hex_code)
                                    <span class="inline-block h-4 w-4 flex-shrink-0 rounded-full border border-zinc-200 dark:border-zinc-600" style="background-color: {{ $color->hex_code }}"></span>
                                @endif
                                {{ $color->name }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $color->hex_code ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $color->products_count }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEdit({{ $color->id }})" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="swatch" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucune couleur.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-sm">
        <flux:heading class="mb-1">{{ $editingId ? __('Modifier la couleur') : __('Nouvelle couleur') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="name" type="text" required autofocus />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Code hex') }}</flux:label>
                <div class="flex items-center gap-2">
                    <flux:input wire:model="hexCode" type="text" placeholder="#RRGGBB" maxlength="7" class="font-mono" />
                    @if (filled($hexCode))
                        <span class="inline-block h-9 w-9 flex-shrink-0 rounded-lg border border-zinc-200 dark:border-zinc-600" style="background-color: {{ $hexCode }}"></span>
                    @endif
                </div>
                <flux:error name="hexCode" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
