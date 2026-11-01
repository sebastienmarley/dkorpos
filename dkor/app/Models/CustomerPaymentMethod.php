<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\CustomerPaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property bool $is_active
 * @property bool $is_usable_on_delivery Le livreur peut l'enregistrer à la livraison (ex. : TPV portatif).
 * @property int|null $ledger_account_id Compte d'encaisse ou de compensation des paiements (vide : Encaisse CAD).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LedgerAccount|null $ledgerAccount
 */
#[Fillable(['code', 'name', 'is_active', 'is_usable_on_delivery', 'ledger_account_id'])]
class CustomerPaymentMethod extends Model
{
    /** @use HasFactory<CustomerPaymentMethodFactory> */
    use HasFactory;

    /** Code du mode « Comptant », géré par l'application. */
    public const CASH = 'cash';

    protected $casts = [
        'is_active' => 'boolean',
        'is_usable_on_delivery' => 'boolean',
    ];

    /**
     * Mode « Comptant » (créé par migration; recréé au besoin).
     */
    public static function cash(): self
    {
        return static::query()->firstOrCreate(['code' => self::CASH], ['name' => 'Comptant', 'is_active' => true]);
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /**
     * Compte où passent les paiements reçus par ce mode : celui choisi, sinon Encaisse CAD.
     */
    public function cashAccount(): LedgerAccount
    {
        return $this->ledgerAccount ?? LedgerAccount::forKey(LedgerAccount::CASH_CAD);
    }

    public function isCash(): bool
    {
        return $this->code === self::CASH;
    }

    /** Un mode géré par l'application ne se modifie ni ne se désactive dans l'interface. */
    public function isSystem(): bool
    {
        return $this->code !== null;
    }

    /**
     * Montant arrondi au 5 ¢ près pour un paiement comptant (0,01-0,02 vers le bas, 0,03-0,04 vers le haut).
     */
    public static function roundCash(Money $amount): Money
    {
        return $amount->roundTo(5);
    }
}
