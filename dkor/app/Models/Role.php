<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string|null $label
 * @property int $level
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'label', 'level', 'guard_name'])]
class Role extends SpatieRole
{
    /**
     * Libellé affiché (le nom technique si aucun libellé n'est défini).
     */
    public function displayName(): string
    {
        return $this->label ?: $this->name;
    }
}
