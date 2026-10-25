<x-layouts::auth :title="__('Authentification à deux facteurs')">
    <div class="flex flex-col gap-6" x-data="{ recovery: false }">
        <x-auth-header
            :title="__('Authentification à deux facteurs')"
            :description="__('Entrez le code à 6 chiffres de votre application d\'authentification')"
        />

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="flex flex-col gap-6">
            @csrf

            <div x-show="! recovery">
                <flux:input
                    name="code"
                    :label="__('Code')"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    x-bind:disabled="recovery"
                />
            </div>

            <div x-show="recovery" x-cloak>
                <flux:input
                    name="recovery_code"
                    :label="__('Code de récupération')"
                    type="text"
                    autocomplete="off"
                    x-bind:disabled="! recovery"
                />
            </div>

            <flux:error name="code" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="two-factor-button">
                {{ __('Se connecter') }}
            </flux:button>
        </form>

        <flux:button variant="ghost" size="sm" x-on:click="recovery = ! recovery">
            <span x-show="! recovery">{{ __('Utiliser un code de récupération') }}</span>
            <span x-show="recovery" x-cloak>{{ __('Utiliser le code de l\'application') }}</span>
        </flux:button>
    </div>
</x-layouts::auth>
