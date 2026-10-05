<flux:modal wire:model="showModal" class="w-full max-w-lg">
    <flux:heading class="mb-1">{{ __('Nouveau produit') }}</flux:heading>

    <form wire:submit="save" class="mt-6 space-y-4">
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
            <flux:label>{{ __('Chercher dans les listes de prix') }}</flux:label>
            <flux:input wire:model.live.debounce.300ms="catalogSearch" icon="magnifying-glass" :placeholder="__('Modèle ou collection…')" :disabled="blank($supplierId)" />
            @if (filled($catalogSearch) && filled($supplierId))
                <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($this->getCatalogResults() as $result)
                        <button type="button" wire:key="catalog-{{ $result->id }}" wire:click="selectFromPriceList({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span class="min-w-0">
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $result->model }}</span>
                                @if ($result->collection)
                                    <span class="text-zinc-500"> · {{ $result->collection }}</span>
                                @endif
                            </span>
                            <span class="flex-shrink-0 text-zinc-500">{{ number_format($result->cost ?? 0, 2) }} $</span>
                        </button>
                    @empty
                        <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun résultat dans les listes de prix actives.') }}</div>
                    @endforelse
                </div>
            @endif
        </flux:field>

        <flux:separator :text="__('ou saisir manuellement')" />

        <flux:field>
            <flux:label>{{ __('Modèle') }}</flux:label>
            <flux:input wire:model="model" type="text" required autofocus />
            <input type="hidden" wire:model="cleanModel" />
            <flux:error name="model" />
        </flux:field>

        <x-cost-input wire:model="cost" label="{{ __('Coût') }}" name="cost" required />

        <div class="flex justify-end gap-3 pt-2">
            <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Créer') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
