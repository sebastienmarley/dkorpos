<flux:modal wire:model="showModal" class="w-full max-w-2xl">
    <flux:heading class="mb-1">{{ $customerId ? __('Modifier le client') : __('Nouveau client') }}</flux:heading>

    <form wire:submit="save" class="mt-6 space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Prénom') }}</flux:label>
                <flux:input wire:model="firstname" type="text" required autofocus />
                <flux:error name="firstname" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="lastname" type="text" required />
                <flux:error name="lastname" />
            </flux:field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-phone-input wire:model="phone" label="{{ __('Téléphone') }}" name="phone" />
            <x-phone-input wire:model="cellphone" label="{{ __('Cellulaire') }}" name="cellphone" />
        </div>

        <flux:field>
            <flux:label>{{ __('Courriel') }}</flux:label>
            <flux:input wire:model="email" type="email" />
            <flux:error name="email" />
        </flux:field>

        <div>
            <flux:heading size="sm" class="mb-4">{{ __('Adresse') }}</flux:heading>
            <x-address-input prefix="address" :current-country="$address['country'] ?? 'CA'" />
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ $customerId ? __('Sauvegarder') : __('Créer') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
