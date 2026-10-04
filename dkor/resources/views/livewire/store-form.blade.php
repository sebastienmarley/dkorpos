<flux:modal wire:model="showModal" class="w-full max-w-lg">
    <flux:heading class="mb-1">{{ __('Nouveau magasin') }}</flux:heading>

    <form wire:submit="save" class="mt-6 space-y-4">
        <flux:field>
            <flux:label>{{ __('Nom de l\'emplacement') }}</flux:label>
            <flux:input wire:model="name" type="text" required autofocus />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Type') }}</flux:label>
            <flux:select wire:model="type">
                @foreach ($this->getStoreTypes() as $storeType)
                    <flux:select.option :value="$storeType->value">{{ $storeType->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="type" />
        </flux:field>

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
