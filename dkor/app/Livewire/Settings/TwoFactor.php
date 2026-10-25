<?php

namespace App\Livewire\Settings;

use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Activation, confirmation et désactivation de la 2FA par l'usager (ADR 0014).
 */
#[Title('Authentification à deux facteurs')]
class TwoFactor extends Component
{
    public string $code = '';

    public bool $showingRecoveryCodes = false;

    /**
     * Activation imposée par EnsureTwoFactorIsEnabled : la page s'affiche sans la navigation de l'application.
     * Fixé au chargement, pour que l'usager voie ses codes de récupération après la confirmation.
     */
    #[Locked]
    public bool $setupRequired = false;

    public function mount(): void
    {
        $user = Auth::user();

        $this->setupRequired = config('access.two_factor.enforced')
            && $user->requiresTwoFactor()
            && ! $user->hasEnabledTwoFactorAuthentication();
    }

    /**
     * Génère un secret ; la 2FA n'est active qu'une fois le premier code confirmé.
     */
    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => ['required', 'string']]);

        $confirm(Auth::user(), $this->code);

        $this->reset('code');
        $this->showingRecoveryCodes = true;

        Flux::toast(variant: 'success', text: __('Authentification à deux facteurs activée.'));
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(Auth::user());

        $this->showingRecoveryCodes = true;
    }

    /**
     * Un usager pour qui la 2FA est obligatoire ne peut pas la désactiver lui-même.
     */
    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        $user = Auth::user();

        abort_if($user->requiresTwoFactor() && $user->hasEnabledTwoFactorAuthentication(), 403);

        $disable($user);

        $this->reset('code', 'showingRecoveryCodes');
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    public function render(): View
    {
        $view = view('livewire.settings.two-factor');

        return $this->setupRequired ? $view->layout('layouts::auth') : $view;
    }
}
