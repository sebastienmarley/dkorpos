<form wire:submit="save" class="my-6 w-full space-y-6">
    <flux:input
        wire:model="pin"
        :label="$hasPin ? __('Nouveau NIP') : __('NIP')"
        :description="__('De 4 à 6 chiffres')"
        type="password"
        inputmode="numeric"
        autocomplete="off"
        maxlength="6"
        viewable
    />

    <flux:input
        wire:model="pinConfirmation"
        :label="__('Confirmer le NIP')"
        type="password"
        inputmode="numeric"
        autocomplete="off"
        maxlength="6"
        viewable
    />

    @if ($hasPin)
        <flux:input wire:model="currentPassword" :label="__('Mot de passe actuel')" type="password" autocomplete="current-password" viewable />
    @endif

    <div class="flex justify-end">
        <flux:button variant="primary" type="submit">{{ $hasPin ? __('Changer le NIP') : __('Enregistrer le NIP') }}</flux:button>
    </div>
</form>
