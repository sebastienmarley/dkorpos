<?php

namespace App\Models;

use App\Enums\Province;
use Database\Factories\TaxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Taux de taxe d'une province, valide pour une période.
 *
 * Un taux sauvegardé est figé et ne se supprime pas : pour le changer, on expire la taxe
 * (date de fin) et on en crée une nouvelle. Ainsi le taux en vigueur à toute date passée reste connu.
 *
 * @property int $id
 * @property Province $province
 * @property string $name
 * @property string $rate Pourcentage (ex. 9.975).
 * @property bool $is_compound Calculée sur le montant plus les taxes individuelles (une après l'autre) plutôt que sur le montant seul.
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['province', 'name', 'rate', 'is_compound', 'start_date', 'end_date'])]
class Tax extends Model
{
    /** @use HasFactory<TaxFactory> */
    use HasFactory;

    /** Date de fin des taxes sans échéance connue. */
    public const DEFAULT_END_DATE = '2100-12-31';

    protected $attributes = [
        'is_compound' => false,
        'end_date' => self::DEFAULT_END_DATE,
    ];

    protected $casts = [
        'province' => Province::class,
        'rate' => 'decimal:3',
        'is_compound' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(function (Tax $tax): void {
            if (array_diff(array_keys($tax->getDirty()), ['end_date', 'updated_at']) !== []) {
                throw new LogicException(__('Une taxe sauvegardée ne peut que expirer : créez-en une nouvelle pour changer le taux.'));
            }
        });

        static::deleting(function (): void {
            throw new LogicException(__('Une taxe ne peut pas être supprimée : faites-la expirer.'));
        });
    }

    /**
     * Taxes en vigueur à une date donnée (début et fin inclus).
     *
     * @param  Builder<Tax>  $query
     * @return Builder<Tax>
     */
    public function scopeActiveOn(Builder $query, Carbon|string $date): Builder
    {
        $day = Carbon::parse($date)->toDateString();

        return $query->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day);
    }

    public function isActiveOn(Carbon|string $date): bool
    {
        $day = Carbon::parse($date)->startOfDay();

        return $this->start_date->lte($day) && $this->end_date->gte($day);
    }

    /**
     * Une taxe de même province et de même nom qui chevauche la période, s'il y en a une.
     */
    public static function overlapping(string $province, string $name, string $start, string $end): ?self
    {
        return static::query()
            ->where('province', $province)
            ->where('name', $name)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->first();
    }
}
