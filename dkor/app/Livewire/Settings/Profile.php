<?php

namespace App\Livewire\Settings;

use App\Actions\ResetUserPassword;
use App\Concerns\ConfirmsCurrentPassword;
use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
class Profile extends Component
{
    use ConfirmsCurrentPassword;
    use ProfileValidationRules;

    public string $firstname = '';

    public string $lastname = '';

    public string $email = '';

    public ?string $generatedPassword = null;

    public bool $showResetPasswordModal = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->firstname = Auth::user()->firstname;
        $this->lastname = Auth::user()->lastname;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

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
