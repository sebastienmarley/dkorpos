<flux:modal wire:model="showModal" class="w-full max-w-lg">
    <flux:heading class="mb-1">{{ __('Nouveau fournisseur') }}</flux:heading>

    <form wire:submit="save" class="mt-6 space-y-4">
        <flux:field>
            <flux:label>{{ __('Type') }}</flux:label>
            <flux:select wire:model="type">
                @foreach ($this->getSupplierTypes() as $supplierType)
                    <flux:select.option :value="$supplierType->value">{{ $supplierType->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Nom') }}</flux:label>
            <flux:input wire:model="name" type="text" required autofocus />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Adresse') }}</flux:label>
            <flux:input wire:model="address" type="text" />
            <flux:error name="address" />
        </flux:field>

        <x-phone-input wire:model="phone" label="{{ __('Téléphone') }}" name="phone" />

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
