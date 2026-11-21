<x-layouts::auth :title="$title">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Session verrouillée')"
            :description="__('Entrez le NIP de :name pour continuer.', ['name' => $user->fullName()])"
        />

        <form method="POST" action="{{ route('session-lock.unlock') }}" class="flex flex-col gap-6">
            @csrf

            {{-- autocomplete="one-time-code" : un code, pas un mot de passe enregistré à proposer (voir partials/session-lock). --}}
            <flux:input
                name="pin"
                :label="__('NIP')"
                type="password"
                inputmode="numeric"
                pattern="[0-9]*"
                autocomplete="one-time-code"
                data-1p-ignore
                data-lpignore="true"
                data-bwignore
                maxlength="6"
                required
                autofocus
            />

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Déverrouiller') }}</flux:button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" icon="arrows-right-left" class="w-full">{{ __('Changer d\'utilisateur') }}</flux:button>
        </form>
    </div>
</x-layouts::auth>
