<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Code postal selon le pays de l'adresse : A1A1A1 au Canada, 12345 aux États-Unis (comme le suggère le gabarit
 * d'adresse).
 */
class PostalCode implements ValidationRule
{
    public function __construct(private readonly ?string $country) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->country === 'US') {
            if (! preg_match('/^\d{5}$/', (string) $value)) {
                $fail(__('Le ZIP Code doit avoir 5 chiffres (ex.: 12345).'));
            }

            return;
        }

        if (! preg_match('/^[A-Z]\d[A-Z]\d[A-Z]\d$/', (string) $value)) {
            $fail(__('Le code postal doit suivre le format A1A1A1.'));
        }
    }
}
