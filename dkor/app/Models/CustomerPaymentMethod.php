<?php

namespace App\Models;

use Database\Factories\CustomerPaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'is_active'])]
class CustomerPaymentMethod extends Model
{
    /** @use HasFactory<CustomerPaymentMethodFactory> */
    use HasFactory;

    /** Code du mode « Comptant », géré par l'application. */
    public const CASH = 'cash';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Mode « Comptant » (créé par migration; recréé au besoin).
     */
    public static function cash(): self
    {
        return static::query()->firstOrCreate(['code' => self::CASH], ['name' => 'Comptant', 'is_active' => true]);
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
    public static function roundCash(float $amount): float
    {
        return round(round($amount * 20) / 20, 2);
    }
}
