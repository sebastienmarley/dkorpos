<?php

namespace App\Livewire\Settings;

use App\Actions\ResetUserPassword;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
class Profile extends Component
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /**
     * Essais de mot de passe permis par minute avant de bloquer la réinitialisation.
     */
    private const RESET_PASSWORD_MAX_ATTEMPTS = 5;

    public string $firstname = '';

    public string $lastname = '';

    public string $email = '';

    public ?string $generatedPassword = null;

    public bool $showResetPasswordModal = false;

    public string $currentPassword = '';

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
     * hors de son compte (ADR 0002), et les essais sont limités pour empêcher de le deviner.
     */
    public function resetPassword(ResetUserPassword $resetUserPassword): void
    {
        $user = Auth::user();
        $throttleKey = 'reset-password:'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, self::RESET_PASSWORD_MAX_ATTEMPTS)) {
            $this->reset('currentPassword');

            throw ValidationException::withMessages([
                'currentPassword' => __('Trop d\'essais. Réessayez dans :seconds secondes.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        try {
            $this->validate(
                ['currentPassword' => $this->currentPasswordRules()],
                [
                    'currentPassword.required' => __('Entrez votre mot de passe actuel.'),
                    'currentPassword.current_password' => __('Le mot de passe est incorrect.'),
                ],
            );
        } catch (ValidationException $e) {
            RateLimiter::hit($throttleKey);
            $this->reset('currentPassword');

            throw $e;
        }

        RateLimiter::clear($throttleKey);

        $this->generatedPassword = $resetUserPassword->execute($user, $user);
        $this->reset('currentPassword', 'showResetPasswordModal');
    }
}
