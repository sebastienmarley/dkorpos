<x-layouts::auth :title="$title">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Session verrouillée')"
            :description="__('Entrez le NIP de :name pour continuer.', ['name' => $user->fullName()])"
        />

        <form method="POST" action="{{ route('session-lock.unlock') }}" class="flex flex-col gap-6">
            @csrf

            {{-- Champ texte masqué : voir partials/session-lock (remplissage automatique du mot de passe). --}}
            <flux:input
                name="pin"
                :label="__('NIP')"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                data-1p-ignore
                data-lpignore="true"
                data-bwignore
                maxlength="6"
                required
                autofocus
                class="[&_input]:[-webkit-text-security:disc]"
            />

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Déverrouiller') }}</flux:button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" icon="arrows-right-left" class="w-full">{{ __('Changer d\'utilisateur') }}</flux:button>
        </form>
    </div>
</x-layouts::auth>
