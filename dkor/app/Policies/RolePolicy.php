<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.manage');
    }

    /**
     * On ne modifie que les rôles dont le niveau ne dépasse pas le sien.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.manage') && $role->level <= $user->roleLevel();
    }

    /**
     * Un rôle encore attribué à des usagers ne peut pas être supprimé.
     */
    public function delete(User $user, Role $role): bool
    {
        return $this->update($user, $role) && $role->users()->doesntExist();
    }
}
