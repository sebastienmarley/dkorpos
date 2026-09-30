<?php

namespace App\Policies;

use App\Enums\RoleType;
use App\Models\User;

class UserPolicy
{
    /**
     * Seuls Admin, Owner et Manager peuvent créer un utilisateur.
     */
    public function create(User $user): bool
    {
        return $user->assignableRoles() !== [];
    }

    /**
     * Un utilisateur ne modifie que les usagers dont il peut attribuer le rôle
     * (un Manager ne touche donc ni aux Admin ni aux Owner) et un Manager ne s'édite pas lui-même.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->role === RoleType::Manager && $user->is($model)) {
            return false;
        }

        return in_array($model->role, $user->assignableRoles(), true);
    }
}
