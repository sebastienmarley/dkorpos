<?php

namespace App\Livewire\Settings;

use App\Actions\ResetUserPassword;
use App\Concerns\ConfirmsCurrentPassword;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
class Profile extends Component
{
    use ConfirmsCurrentPassword;

    public ?string $generatedPassword = null;

    public bool $showResetPasswordModal = false;

    /**
     * Génère un nouveau mot de passe pour l'usager connecté, selon la même logique que la fiche usager.
     * Le mot de passe actuel est exigé pour qu'une session laissée ouverte ne permette pas de bloquer l'usager
     * hors de son compte (ADR 0002).
     */
    public function resetPassword(ResetUserPassword $resetUserPassword): void
    {
        $this->confirmCurrentPassword('reset-password');

        $user = Auth::user();

        $this->generatedPassword = $resetUserPassword->execute($user, $user);
        $this->reset('showResetPasswordModal');
    }
}
