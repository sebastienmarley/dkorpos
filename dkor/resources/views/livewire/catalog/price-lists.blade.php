<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Listes de prix') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Listes de prix actives des fournisseurs de produits') }}</flux:text>
        </div>
        @can('price_lists.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Ajouter une liste') }}
            </flux:button>
        @endcan
    </div>

    <div class="mb-4 max-w-sm">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Rechercher un fournisseur…')" clearable />
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                <flux:table.column>{{ __('Liste active') }}</flux:table.column>
                <flux:table.column>{{ __('Début') }}</flux:table.column>
                <flux:table.column>{{ __('Fin') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($priceLists as $list)
                    <flux:table.row :key="$list->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('catalog.price-lists.show', $list) }}" wire:navigate class="hover:underline">{{ $list->supplier->name }}</a>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($list->isUpcoming())
                                <flux:badge color="blue" size="sm">{{ __('À venir') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $list->starts_on->toDateString() }}</flux:table.cell>
                        <flux:table.cell>{{ $list->ends_on->toDateString() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-12 text-center">
                            <flux:text class="text-zinc-400">
                                {{ filled($search) ? __('Aucune liste de prix active pour ce fournisseur.') : __('Recherchez un fournisseur pour voir sa liste de prix active.') }}
                            </flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <flux:heading class="mb-1">{{ __('Nouvelle liste de prix') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Fournisseur') }}</flux:label>
                <flux:select wire:model="supplierId">
                    <flux:select.option value="">{{ __('Sélectionner…') }}</flux:select.option>
                    @foreach ($creatableSuppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="supplierId" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Date de début') }}</flux:label>
                    <flux:input wire:model="startsOn" type="date" required />
                    <flux:error name="startsOn" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date de fin') }}</flux:label>
                    <flux:input wire:model="endsOn" type="date" required />
                    <flux:error name="endsOn" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
