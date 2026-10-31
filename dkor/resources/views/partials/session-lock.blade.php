{{-- Écran de verrouillage superposé à la page (ADR 0015) : la page garde son état pendant le verrouillage. Le serveur
     refuse quand même toute requête d'une session verrouillée ; cet écran n'est pas la protection.
     Un <dialog> ouvert par showModal() passe par-dessus les modals Flux déjà ouverts et rend le reste de la page inerte.
     Chrome laisse Échap le fermer malgré cancel.prevent : il est rouvert tant que la session est verrouillée. --}}
@if (app(\App\Support\SessionLock::class)->appliesTo(auth()->user()))
    <dialog
        x-data="sessionLock(@js([
            'idleSeconds' => app(\App\Support\SessionLock::class)->idleSeconds(),
            'csrfToken' => csrf_token(),
            'lockUrl' => route('session-lock.lock'),
            'unlockUrl' => route('session-lock.unlock'),
            'heartbeatUrl' => route('session-lock.heartbeat'),
            'locked' => app(\App\Support\SessionLock::class)->isLocked(),
        ]))"
        x-on:lock-session.window="lock()"
        x-on:session-locked.window="showLock()"
        x-on:cancel.prevent
        x-on:close="locked && showLock()"
        class="m-0 h-dvh max-h-none w-dvw max-w-none items-center justify-center bg-white p-6 open:flex dark:bg-zinc-900"
        aria-labelledby="session-lock-title"
    >
        <div class="flex w-full max-w-sm flex-col gap-6">
            <div class="flex flex-col items-center gap-2 text-center">
                <flux:icon name="lock-closed" class="size-9 text-zinc-500" />
                <flux:heading size="xl" id="session-lock-title">{{ __('Session verrouillée') }}</flux:heading>
                <flux:subheading>{{ __('Entrez le NIP de :name pour continuer.', ['name' => auth()->user()->fullName()]) }}</flux:subheading>
            </div>

            <form x-on:submit.prevent="unlock()" class="flex flex-col gap-6">
                <flux:field>
                    <flux:label>{{ __('NIP') }}</flux:label>
                    <flux:input x-ref="pin" x-model="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" required />
                    <p x-show="error" x-text="error" role="alert" class="mt-3 text-sm font-medium text-red-500 dark:text-red-400"></p>
                </flux:field>

                <flux:button variant="primary" type="submit" class="w-full" x-bind:disabled="submitting">{{ __('Déverrouiller') }}</flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button type="submit" variant="ghost" icon="arrows-right-left" class="w-full">{{ __('Changer d\'utilisateur') }}</flux:button>
            </form>
        </div>
    </dialog>
@endif
