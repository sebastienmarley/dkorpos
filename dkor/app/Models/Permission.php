<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string|null $label
 * @property string|null $description
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'label', 'description', 'guard_name'])]
class Permission extends SpatiePermission
{
    /**
     * Libellé affiché (le nom technique si aucun libellé n'est défini).
     */
    public function displayName(): string
    {
        return $this->label ?: $this->name;
    }

    /**
     * Groupe de la permission : la partie avant le premier point (users.create → users).
     */
    public function group(): string
    {
        return str($this->name)->before('.')->value();
    }
}
