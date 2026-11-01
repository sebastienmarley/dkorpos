<?php

namespace App\Models;

use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Écriture manuelle du journal général (ADR 0016) : ce qui n'a pas de document dans l'application (soldes
 * d'ouverture, dépôts des processeurs de paiement, virements, frais bancaires, paie). Son écriture au grand livre la
 * référence; elle ne se modifie pas, elle se renverse.
 *
 * @property int $id
 * @property Carbon $entry_date
 * @property string $description
 * @property string|null $document_number Pièce justificative (relevé, bordereau…).
 * @property int|null $store_id
 * @property int|null $reverses_id Écriture manuelle que celle-ci renverse.
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LedgerEntry|null $ledgerEntry
 * @property-read JournalEntry|null $reversed
 * @property-read JournalEntry|null $reversal
 * @property-read Store|null $store
 * @property-read User|null $creator
 */
#[Fillable(['entry_date', 'description', 'document_number', 'store_id', 'reverses_id', 'created_by'])]
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    protected $casts = [
        'entry_date' => 'date',
    ];

    /** @return MorphOne<LedgerEntry, $this> */
    public function ledgerEntry(): MorphOne
    {
        return $this->morphOne(LedgerEntry::class, 'reference');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function reversed(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reverses_id');
    }

    /** @return HasOne<JournalEntry, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(JournalEntry::class, 'reverses_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
