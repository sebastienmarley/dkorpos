<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profil')" :subheading="__('Informations de votre compte')">
        <div class="my-6 w-full space-y-6">
            <flux:input :value="Auth::user()->firstname . ' ' . Auth::user()->lastname" :label="__('Nom')" type="text" readonly />
            <flux:input :value="Auth::user()->email" :label="__('Courriel')" type="email" readonly />
        </div>

        <flux:separator class="my-6" />

        {{-- Réinitialisation du mot de passe --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="sm">{{ __('Mot de passe') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Générer un nouveau mot de passe sécuritaire') }}</flux:text>
            </div>
            <flux:button type="button" variant="danger" icon="key" wire:click="$set('showResetPasswordModal', true)">
                {{ __('Réinitialiser') }}
            </flux:button>
        </div>

        <flux:modal wire:model="showResetPasswordModal" class="w-full max-w-lg">
            <form wire:submit="resetPassword" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Réinitialiser votre mot de passe ?') }}</flux:heading>
                    <flux:subheading>{{ __('Un nouveau mot de passe sera généré. Entrez votre mot de passe actuel pour confirmer.') }}</flux:subheading>
                </div>

                <flux:input wire:model="currentPassword" :label="__('Mot de passe actuel')" type="password" autocomplete="current-password" viewable />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Annuler') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" type="submit">{{ __('Réinitialiser') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        @if ($generatedPassword)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950">
                <div class="mb-2 flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    <flux:text class="text-sm font-medium text-amber-800 dark:text-amber-300">
                        {{ __('Notez ce mot de passe, il ne sera plus affiché.') }}
                    </flux:text>
                </div>
                <div class="flex items-center justify-between rounded-md border border-amber-300 bg-white px-3 py-2 dark:border-amber-600 dark:bg-zinc-900">
                    <code class="text-base font-mono font-semibold tracking-wider text-zinc-800 dark:text-zinc-100">{{ $generatedPassword }}</code>
                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        icon="clipboard"
                        x-on:click="navigator.clipboard.writeText(@js($generatedPassword))"
                    >
                        {{ __('Copier') }}
                    </flux:button>
                </div>
            </div>
        @endif

        <flux:separator class="my-6" />

        <flux:heading class="mb-4">{{ __('Apparence') }}</flux:heading>
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Clair') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Sombre') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('Système') }}</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</section>
