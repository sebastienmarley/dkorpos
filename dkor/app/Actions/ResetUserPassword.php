<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetUserPassword
{
    /**
     * Remplace le mot de passe par un mot de passe sécuritaire généré (ADR 0002).
     *
     * @return string Le mot de passe en clair, à afficher une seule fois
     */
    public function execute(User $user, ?User $modifier): string
    {
        $plainPassword = Str::password(12);

        $user->password = Hash::make($plainPassword);
        $user->markModifiedBy($modifier)->save();

        return $plainPassword;
    }
}
