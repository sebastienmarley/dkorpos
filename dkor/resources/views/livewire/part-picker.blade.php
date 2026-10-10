<flux:modal wire:model="showModal" class="w-full max-w-lg">
    <flux:heading class="mb-1">{{ __('Ajouter une pièce') }}</flux:heading>

    @if (! $creating)
        <flux:text class="text-zinc-500">{{ __('Cherchez par modèle, description ou produit (SKU).') }}</flux:text>

        <div class="mt-6 space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Modèle, description ou produit…')" autofocus />

            @if (mb_strlen(trim($search)) >= $minSearchLength)
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($results as $part)
                        <button type="button" wire:key="part-result-{{ $part->id }}" wire:click="select({{ $part->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span class="min-w-0">
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $part->model }}</span>
                                <span class="text-zinc-500"> · {{ $part->description }}</span>
                                <span class="block text-xs text-zinc-400">{{ $part->supplier->name }}</span>
                            </span>
                            <span class="flex-shrink-0 text-zinc-500">{{ number_format($part->last_cost, 2) }} $</span>
                        </button>
                    @empty
                        <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucune pièce trouvée.') }}</div>
                    @endforelse
                </div>
            @endif

            @can('parts.create')
                <div class="flex justify-end">
                    <flux:button type="button" icon="plus" wire:click="startCreating">{{ __('Créer une nouvelle pièce') }}</flux:button>
                </div>
            @endcan
        </div>
    @else
        <flux:text class="text-zinc-500">{{ __('Si ce fournisseur a déjà une pièce de ce modèle, elle sera sélectionnée au lieu d\'en créer une autre.') }}</flux:text>

        <form wire:submit="create" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Fournisseur') }}</flux:label>
                <flux:select wire:model="supplierId">
                    <flux:select.option value="">{{ __('Sélectionner un fournisseur…') }}</flux:select.option>
                    @foreach ($this->getSuppliers() as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="supplierId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Modèle') }}</flux:label>
                <flux:input wire:model="model" type="text" required />
                <flux:error name="model" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:input wire:model="description" type="text" required :placeholder="__('Ex. : verre de remplacement pour lampe')" />
                <flux:error name="description" />
            </flux:field>

            <x-cost-input wire:model="lastCost" label="{{ __('Coût') }}" name="lastCost" />

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="cancelCreating">
                    {{ __('Retour à la recherche') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Créer') }}
                </flux:button>
            </div>
        </form>
    @endif
</flux:modal>
