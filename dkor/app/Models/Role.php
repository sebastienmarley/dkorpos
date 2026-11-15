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

    /**
     * Libellé au pluriel (« Vendeurs »), défini dans config/access.php. Pour un rôle créé dans
     * l'interface, on ajoute un « s » au premier mot du libellé s'il n'en a pas déjà.
     */
    public function pluralDisplayName(): string
    {
        $configured = config("access.roles.{$this->name}.plural_label");

        if (filled($configured)) {
            return $configured;
        }

        $words = explode(' ', $this->displayName(), 2);

        if (! preg_match('/[sxz]$/iu', $words[0])) {
            $words[0] .= 's';
        }

        return implode(' ', $words);
    }
}
