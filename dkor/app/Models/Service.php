<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Service vendu au client. Interne : rendu par le magasin, au prix du service. Externe : offert par des
 * fournisseurs, chacun avec son coût et son prix vendant.
 *
 * @property int $id
 * @property string|null $code Service géré par l'application (ex. : frais d'expédition), qui ne s'archive pas.
 * @property string $name
 * @property string|null $description_template
 * @property bool $is_internal
 * @property Money|null $selling_price
 * @property bool $is_taxable
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Supplier> $suppliers
 */
#[Fillable(['code', 'name', 'description_template', 'is_internal', 'selling_price', 'is_taxable', 'is_active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /** Frais d'expédition : service interne à prix fixe, proposé à chaque expédition (issue #86). */
    public const string SHIPPING_FEE = 'shipping_fee';

    protected $attributes = [
        'is_internal' => false,
        'is_taxable' => true,
        'is_active' => true,
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'selling_price' => MoneyCast::class,
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Frais d'expédition (créé par ReferenceDataSeeder; recréé au besoin). Son prix se règle dans la page Services.
     */
    public static function shippingFee(): self
    {
        return static::query()->firstOrCreate(
            ['code' => self::SHIPPING_FEE],
            ['name' => __('Frais d\'expédition'), 'is_internal' => true, 'selling_price' => Money::zero(), 'is_taxable' => true, 'is_active' => true],
        );
    }

    /** Un service géré par l'application reste interne et actif : il ne s'archive pas. */
    public function isSystem(): bool
    {
        return $this->code !== null;
    }

    /**
     * Fournisseurs qui offrent ce service (pivot : cost, selling_price).
     *
     * @return BelongsToMany<Supplier, $this, ServiceSupplier>
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)->using(ServiceSupplier::class)->withPivot(['id', 'cost', 'selling_price'])->withTimestamps();
    }

    /**
     * Prix vendant et coût du service chez ce fournisseur (ou au magasin pour un service interne).
     *
     * @return array{selling_price: Money, cost: Money}
     */
    public function pricingFor(?int $supplierId): array
    {
        if ($this->is_internal || $supplierId === null) {
            return ['selling_price' => $this->selling_price ?? Money::zero(), 'cost' => Money::zero()];
        }

        $offer = ServiceSupplier::query()->where('service_id', $this->id)->where('supplier_id', $supplierId)->first();

        return ['selling_price' => $offer->selling_price ?? Money::zero(), 'cost' => $offer->cost ?? Money::zero()];
    }

    /**
     * Description de départ d'une vente : le modèle, où {produit} est remplacé par le produit visé (s'il y a lieu).
     */
    public function describe(?string $productName = null): string
    {
        $template = filled($this->description_template) ? $this->description_template : $this->name;

        return trim(str_replace('{produit}', $productName ?? '', $template));
    }
}
