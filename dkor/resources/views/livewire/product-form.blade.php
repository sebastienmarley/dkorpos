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
