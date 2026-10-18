<?php

namespace App\Models;

use App\Enums\LedgerAccountType;
use Database\Factories\LedgerAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Compte du grand livre (ADR 0011). Un compte système a une clé fixe dont l'application se sert; son code et son nom
 * suivent le plan comptable du comptable.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property LedgerAccountType $type
 * @property string|null $system_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'type', 'system_key'])]
class LedgerAccount extends Model
{
    /** @use HasFactory<LedgerAccountFactory> */
    use HasFactory;

    public const INVENTORY = 'inventory';

    public const INVENTORY_SHRINKAGE = 'inventory_shrinkage';

    public const STORE_SUPPLIES = 'store_supplies';

    public const INVENTORY_COUNT_VARIANCE = 'inventory_count_variance';

    public const OPENING_BALANCE = 'opening_balance';

    protected $casts = [
        'type' => LedgerAccountType::class,
    ];

    /**
     * Code, nom et type de départ des comptes système.
     *
     * @return array<string, array{0: string, 1: string, 2: LedgerAccountType}>
     */
    public static function systemAccounts(): array
    {
        return [
            self::INVENTORY => ['1300', __('Stock de marchandises'), LedgerAccountType::Asset],
            self::OPENING_BALANCE => ['3900', __('Solde d\'ouverture'), LedgerAccountType::Equity],
            self::INVENTORY_SHRINKAGE => ['5110', __('Pertes d\'inventaire'), LedgerAccountType::Expense],
            self::INVENTORY_COUNT_VARIANCE => ['5120', __('Écart d\'inventaire'), LedgerAccountType::Expense],
            self::STORE_SUPPLIES => ['5400', __('Fournitures du magasin'), LedgerAccountType::Expense],
        ];
    }

    /**
     * Crée les comptes système qui manquent (ReferenceDataSeeder), sans toucher à ceux qui existent.
     */
    public static function ensureSystemAccounts(): void
    {
        foreach (array_keys(self::systemAccounts()) as $key) {
            self::forKey($key);
        }
    }

    public static function forKey(string $key): self
    {
        [$code, $name, $type] = self::systemAccounts()[$key];

        return static::query()->firstOrCreate(['system_key' => $key], ['code' => $code, 'name' => $name, 'type' => $type]);
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    /** @return HasMany<LedgerEntryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(LedgerEntryLine::class);
    }
}
