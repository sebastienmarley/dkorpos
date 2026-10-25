<div class="my-6 w-full space-y-6">
    @if ($this->user->requiresTwoFactor() && ! $this->user->hasEnabledTwoFactorAuthentication())
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('L\'authentification à deux facteurs est obligatoire pour votre compte.')" />
    @endif

    @if ($this->user->hasEnabledTwoFactorAuthentication())
        <flux:badge color="green" icon="shield-check">{{ __('Activée') }}</flux:badge>

        @if ($showingRecoveryCodes)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950">
                <flux:text class="mb-3 text-sm font-medium text-amber-800 dark:text-amber-300">
                    {{ __('Gardez ces codes de récupération en lieu sûr. Chacun permet une connexion si vous perdez votre téléphone.') }}
                </flux:text>
                <div class="grid grid-cols-2 gap-2 font-mono text-sm" data-test="recovery-codes">
                    @foreach ($this->user->recoveryCodes() as $recoveryCode)
                        <code>{{ $recoveryCode }}</code>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex flex-wrap gap-2">
            @unless ($showingRecoveryCodes)
                <flux:button wire:click="$set('showingRecoveryCodes', true)">{{ __('Afficher les codes de récupération') }}</flux:button>
            @endunless
            <flux:button wire:click="regenerateRecoveryCodes" wire:confirm="{{ __('Les anciens codes ne fonctionneront plus. Continuer ?') }}">
                {{ __('Générer de nouveaux codes') }}
            </flux:button>
            @unless ($this->user->requiresTwoFactor())
                <flux:button variant="danger" wire:click="disable" wire:confirm="{{ __('Désactiver l\'authentification à deux facteurs ?') }}">
                    {{ __('Désactiver') }}
                </flux:button>
            @endunless
        </div>
    @elseif ($this->user->two_factor_secret)
        <flux:text>{{ __('Scannez ce code avec votre application d\'authentification (Google Authenticator, Microsoft Authenticator, 1Password…), puis entrez le code à 6 chiffres qu\'elle affiche.') }}</flux:text>

        <div class="inline-block rounded-lg bg-white p-4">
            {!! $this->user->twoFactorQrCodeSvg() !!}
        </div>

        <flux:text class="text-sm">
            {{ __('Ou entrez cette clé :') }}
            <code class="font-mono font-semibold">{{ decrypt($this->user->two_factor_secret) }}</code>
        </flux:text>

        <form wire:submit="confirm" class="flex items-end gap-2">
            <flux:input wire:model="code" :label="__('Code')" inputmode="numeric" autocomplete="one-time-code" class="max-w-40" />
            <flux:button type="submit" variant="primary">{{ __('Confirmer') }}</flux:button>
            <flux:button wire:click="disable">{{ __('Annuler') }}</flux:button>
        </form>
        <flux:error name="code" />
    @else
        <flux:button variant="primary" icon="shield-check" wire:click="enable">{{ __('Activer') }}</flux:button>
    @endif
</div>
