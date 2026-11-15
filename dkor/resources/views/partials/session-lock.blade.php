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
                    {{-- Champ texte masqué plutôt que type="password" : le navigateur et les gestionnaires de mots de passe y
                         injectaient le mot de passe enregistré (tronqué à 6 caractères), et le NIP tapé était refusé. --}}
                    <flux:input x-ref="pin" x-model="pin" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-1p-ignore data-lpignore="true" data-bwignore maxlength="6" required class="[&_input]:[-webkit-text-security:disc]" />
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
