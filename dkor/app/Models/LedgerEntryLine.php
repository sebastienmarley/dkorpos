<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\LedgerEntryLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $ledger_entry_id
 * @property int $ledger_account_id
 * @property Money $debit
 * @property Money $credit
 * @property-read LedgerEntry $entry
 * @property-read LedgerAccount $account
 */
#[Fillable(['ledger_entry_id', 'ledger_account_id', 'debit', 'credit'])]
class LedgerEntryLine extends Model
{
    /** @use HasFactory<LedgerEntryLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $casts = [
        'debit' => MoneyCast::class,
        'credit' => MoneyCast::class,
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Une écriture du grand livre ne se modifie pas.'));
        static::deleting(fn () => throw new LogicException('Une écriture du grand livre ne se supprime pas.'));
    }

    /** @return BelongsTo<LedgerEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'ledger_entry_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
