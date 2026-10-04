<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Créer un utilisateur exige la permission et au moins un rôle attribuable.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create') && $user->assignableRoles()->isNotEmpty();
    }

    /**
     * Sa propre fiche dépend uniquement de users.edit_self. Pour les autres, il faut users.edit
     * et que leur rôle ne dépasse pas le niveau hiérarchique de l'utilisateur.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return $user->can('users.edit_self');
        }

        return $user->can('users.edit')
            && ($model->role === null || $user->assignableRoles()->contains('id', $model->role->id));
    }

    /**
     * Attribuer des permissions supplémentaires à un usager que l'on peut modifier.
     */
    public function assignPermissions(User $user, User $model): bool
    {
        return $user->can('users.assign_permissions')
            && ! $user->is($model)
            && $this->update($user, $model);
    }

    /**
     * L'onglet RH est réservé à users.view_hr, sans règle de hiérarchie (la comptabilité voit tous les employés).
     */
    public function viewHr(User $user, User $model): bool
    {
        return $user->can('users.view_hr') && $user->canSeeRole($model->role);
    }

    /**
     * Modifier l'onglet RH exige users.edit_hr, et personne ne modifie ses propres informations RH.
     */
    public function editHr(User $user, User $model): bool
    {
        return $user->can('users.edit_hr') && ! $user->is($model) && $this->viewHr($user, $model);
    }
}
