<?php

namespace App\Livewire\Settings;

use App\Concerns\ConfirmsCurrentPassword;
use App\Support\SessionLock;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Création et changement du NIP qui déverrouille la session après inactivité (ADR 0015).
 */
#[Title('NIP')]
class Pin extends Component
{
    use ConfirmsCurrentPassword;

    public string $pin = '';

    public string $pinConfirmation = '';

    /**
     * Création imposée par EnsurePinIsSet : la page s'affiche sans la navigation de l'application.
     */
    #[Locked]
    public bool $setupRequired = false;

    public function mount(): void
    {
        $this->setupRequired = config('access.pin.enforced') && Auth::user()->pin === null;
    }

    /**
     * Le mot de passe actuel est exigé pour changer un NIP existant, pas pour le créer juste après la connexion.
     */
    public function save(SessionLock $sessionLock): void
    {
        $user = Auth::user();

        $this->validate(
            [
                'pin' => ['required', 'digits_between:4,6'],
                'pinConfirmation' => ['required', 'same:pin'],
            ],
            [
                'pin.required' => __('Entrez un NIP.'),
                'pin.digits_between' => __('Le NIP doit contenir de 4 à 6 chiffres.'),
                'pinConfirmation.required' => __('Confirmez le NIP.'),
                'pinConfirmation.same' => __('Les deux NIP ne correspondent pas.'),
            ],
        );

        $isCreatingPin = $user->pin === null;

        if (! $isCreatingPin) {
            $this->confirmCurrentPassword('change-pin');
        }

        $user->pin = $this->pin;
        $user->save();

        // L'activité n'est pas suivie tant que l'usager n'a pas de NIP : le verrouillage démarre maintenant.
        if ($isCreatingPin) {
            $sessionLock->unlock();
        }

        $this->reset('pin', 'pinConfirmation');

        if ($this->setupRequired) {
            $this->redirectIntended(route('home'), navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: __('NIP enregistré.'));
    }

    public function render(): View
    {
        $view = view('livewire.settings.pin', ['hasPin' => Auth::user()->pin !== null]);

        return $this->setupRequired ? $view->layout('layouts::auth') : $view;
    }
}
