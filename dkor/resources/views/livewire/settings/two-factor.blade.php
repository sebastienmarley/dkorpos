{{-- Racine unique hors du @if : Livewire pose son wire:id sur le premier élément du composant. --}}
<div @class(['flex flex-col gap-6' => $setupRequired, 'w-full' => ! $setupRequired])>
    @if ($setupRequired)
        <x-auth-header
            :title="__('Authentification à deux facteurs')"
            :description="__('Un code de votre téléphone est demandé à chaque connexion, en plus du mot de passe')"
        />

        @include('livewire.settings.partials.two-factor-form')

        <div class="flex items-center justify-between">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button type="submit" variant="ghost" size="sm">{{ __('Se déconnecter') }}</flux:button>
            </form>

            @if ($this->user->hasEnabledTwoFactorAuthentication())
                <flux:button variant="primary" :href="route('home')">{{ __('Continuer') }}</flux:button>
            @endif
        </div>
    @else
        @include('partials.settings-heading')

        <flux:heading level="2" class="sr-only">{{ __('Authentification à deux facteurs') }}</flux:heading>

        <x-settings.layout :heading="__('Authentification à deux facteurs')" :subheading="__('Un code de votre téléphone est demandé à chaque connexion, en plus du mot de passe')">
            @include('livewire.settings.partials.two-factor-form')
        </x-settings.layout>
    @endif
</div>
