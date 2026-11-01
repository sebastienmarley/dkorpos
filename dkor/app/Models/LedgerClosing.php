<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\LedgerClosingFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Clôture ou réouverture du grand livre (ADR 0016). La dernière ligne donne la date jusqu'à laquelle le grand livre
 * est clôturé : aucune écriture n'est datée dans une période clôturée, elle est reportée au premier jour ouvert.
 *
 * @property int $id
 * @property CarbonImmutable|null $closed_through
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['closed_through', 'user_id'])]
class LedgerClosing extends Model
{
    /** @use HasFactory<LedgerClosingFactory> */
    use HasFactory;

    protected $casts = [
        'closed_through' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Date jusqu'à laquelle le grand livre est clôturé, ou null s'il est entièrement ouvert.
     */
    public static function closedThrough(): ?CarbonImmutable
    {
        return static::query()->latest('id')->first()?->closed_through;
    }

    /**
     * Date d'une écriture : la date du document, ou le premier jour ouvert si elle tombe dans une période clôturée.
     */
    public static function openDateFor(CarbonInterface $date): CarbonImmutable
    {
        $closedThrough = self::closedThrough();

        return $closedThrough !== null && $date->lessThanOrEqualTo($closedThrough)
            ? $closedThrough->addDay()
            : CarbonImmutable::parse($date)->startOfDay();
    }

    /**
     * Clôture le grand livre jusqu'à cette date (incluse), après la clôture actuelle et avant aujourd'hui.
     *
     * @throws DomainException si la date ne suit pas la clôture actuelle ou n'est pas passée.
     */
    public static function close(CarbonInterface $through, ?User $user = null): self
    {
        $current = self::closedThrough();

        if ($current !== null && $through->lessThanOrEqualTo($current)) {
            throw new DomainException(__('Le grand livre est déjà clôturé jusqu\'au :date.', ['date' => $current->toDateString()]));
        }

        if (! $through->lessThan(today())) {
            throw new DomainException(__('On ne clôture qu\'une période terminée (avant aujourd\'hui).'));
        }

        return static::create(['closed_through' => $through->toDateString(), 'user_id' => $user?->id]);
    }

    /**
     * Rouvre le grand livre à partir de cette date : la clôture recule à la veille.
     *
     * @throws DomainException si rien n'est clôturé à cette date.
     */
    public static function reopenFrom(CarbonInterface $from, ?User $user = null): self
    {
        $current = self::closedThrough();

        if ($current === null || $from->greaterThan($current)) {
            throw new DomainException(__('Cette date n\'est pas dans une période clôturée.'));
        }

        return static::create(['closed_through' => CarbonImmutable::parse($from)->subDay()->toDateString(), 'user_id' => $user?->id]);
    }
}
