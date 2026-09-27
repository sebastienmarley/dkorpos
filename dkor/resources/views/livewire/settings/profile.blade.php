<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profil')" :subheading="__('Informations de votre compte')">
        <div class="my-6 w-full space-y-6">
            <flux:input :value="Auth::user()->firstname . ' ' . Auth::user()->lastname" :label="__('Nom')" type="text" readonly />
            <flux:input :value="Auth::user()->email" :label="__('Courriel')" type="email" readonly />
        </div>

        <flux:separator class="my-6" />

        <flux:heading class="mb-4">{{ __('Apparence') }}</flux:heading>
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Clair') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Sombre') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('Système') }}</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</section>
