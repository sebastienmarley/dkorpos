<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Confirmation du mot de passe actuel avant une action sensible d'un composant Livewire. Une session laissée ouverte ne
 * permet pas de prendre le contrôle du compte, et les essais sont limités pour empêcher de deviner le mot de passe.
 */
trait ConfirmsCurrentPassword
{
    use PasswordValidationRules;

    /**
     * Essais de mot de passe permis par minute avant de bloquer l'action.
     */
    private const CURRENT_PASSWORD_MAX_ATTEMPTS = 5;

    public string $currentPassword = '';

    /**
     * @param  string  $action  Distingue le compteur d'essais de chaque action.
     *
     * @throws ValidationException
     */
    protected function confirmCurrentPassword(string $action): void
    {
        $throttleKey = $action.':'.Auth::id();

        if (RateLimiter::tooManyAttempts($throttleKey, self::CURRENT_PASSWORD_MAX_ATTEMPTS)) {
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
        $this->reset('currentPassword');
    }
}
