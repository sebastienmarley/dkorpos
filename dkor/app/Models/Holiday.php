<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $date
 * @property string $name
 * @property bool $is_closed Magasin fermé pour tous ; sinon férié ouvert, attribué comme un congé.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['date', 'name', 'is_closed'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
        ];
    }

    /**
     * Toujours au format Y-m-d, même si la base renvoie un horodatage.
     *
     * @return Attribute<string, string>
     */
    protected function date(): Attribute
    {
        return Attribute::make(get: fn (string $value): string => substr($value, 0, 10));
    }
}
