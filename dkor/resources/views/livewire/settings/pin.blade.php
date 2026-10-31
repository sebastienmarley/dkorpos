{{-- Racine unique hors du @if : Livewire pose son wire:id sur le premier élément du composant. --}}
<div @class(['flex flex-col gap-6' => $setupRequired, 'w-full' => ! $setupRequired])>
    @if ($setupRequired)
        <x-auth-header
            :title="__('Créez votre NIP')"
            :description="__('Votre session se verrouille après quelques minutes d\'inactivité. Votre NIP la déverrouille sans perdre votre travail.')"
        />

        @include('livewire.settings.partials.pin-form')

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" size="sm">{{ __('Se déconnecter') }}</flux:button>
        </form>
    @else
        @include('partials.settings-heading')

        <flux:heading level="2" class="sr-only">{{ __('NIP') }}</flux:heading>

        <x-settings.layout :heading="__('NIP')" :subheading="__('Déverrouille votre session après quelques minutes d\'inactivité')">
            @include('livewire.settings.partials.pin-form')
        </x-settings.layout>
    @endif
</div>
