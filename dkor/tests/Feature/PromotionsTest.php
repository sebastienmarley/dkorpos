<?php

use App\Enums\PromotionType;
use App\Livewire\Catalog\Promotions;
use App\Livewire\CustomerOrders\Show;
use App\Models\Category;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/**
 * Ligne en stock au prix donné, d'un produit dont le prix de vente est plus bas : la ligne n'est pas déjà escomptée.
 */
function regularLine(CustomerOrder $order, int $quantity, float $price, array $productAttributes = []): CustomerOrderLine
{
    $line = stockedLine($order, $quantity, $quantity, $price);
    $line->product->update(['cost' => Money::of(100), ...$productAttributes]);

    return $line->fresh();
}

/** Promotion qui vise le modèle donné (produit, fournisseur, catégorie ou département). */
function promotionOn(Model $target, array $attributes = [], ?Closure $state = null): Promotion
{
    $factory = Promotion::factory();
    $promotion = ($state ? $state($factory) : $factory)->create($attributes);
    $promotion->targets()->create(['target_type' => $target->getMorphClass(), 'target_id' => $target->getKey()]);

    return $promotion;
}

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
});

describe('application à une commande', function () {
    it('baisse du pourcentage le prix des lignes visées et garde leur prix régulier', function () {
        $department = Department::factory()->create();
        $category = Category::factory()->create(['department_id' => $department->id]);
        $line = regularLine($this->order, 1, 100, ['category_id' => $category->id]);
        $promotion = promotionOn($department, ['percent' => 10]);

        $this->order->applyPromotion($promotion);

        expect($line->fresh())
            ->unit_price->toBeMoney(9000)
            ->regular_unit_price->toBeMoney(10000)
            ->promotion_id->toBe($promotion->id)
            ->and($this->order->fresh())
            ->promotion_id->toBe($promotion->id)
            ->total->toBeMoney(10348);
    });

    it('ne touche ni les produits déjà escomptés ni les produits hors promotion', function () {
        $supplierLine = regularLine($this->order, 1, 100);
        $discountedLine = stockedLine($this->order, 1, 1, 100);
        $discountedLine->product->update(['supplier_id' => $supplierLine->product->supplier_id, 'cost' => Money::of(20000)]);
        $otherLine = regularLine($this->order, 1, 100);

        $this->order->applyPromotion(promotionOn($supplierLine->product->supplier, ['percent' => 20]));

        expect($supplierLine->fresh()->unit_price)->toBeMoney(8000)
            ->and($discountedLine->fresh())->unit_price->toBeMoney(10000)->promotion_id->toBeNull()
            ->and($otherLine->fresh())->unit_price->toBeMoney(10000)->promotion_id->toBeNull();
    });

    it('répartit un montant fixe au prorata des articles visés, à partir du nombre requis', function () {
        $first = regularLine($this->order, 1, 300);
        $second = regularLine($this->order, 1, 100, ['supplier_id' => $first->product->supplier_id]);
        $promotion = promotionOn($first->product->supplier, state: fn ($factory) => $factory->fixedAmount(100, 2));

        $this->order->applyPromotion($promotion);

        expect($first->fresh()->unit_price)->toBeMoney(22500)
            ->and($second->fresh()->unit_price)->toBeMoney(7500);
    });

    it('n\'offre pas le montant fixe sous le nombre d\'articles requis', function () {
        $line = regularLine($this->order, 1, 300);
        $promotion = promotionOn($line->product, state: fn ($factory) => $factory->fixedAmount(100, 2));

        expect($promotion->isApplicableTo($this->order))->toBeFalse()
            ->and(fn () => $this->order->applyPromotion($promotion))->toThrow(DomainException::class);
    });

    it('paie les taxes des articles taxables visés : leur total taxes incluses revient au prix avant taxes', function () {
        $taxable = regularLine($this->order, 1, 100);
        $exempt = regularLine($this->order, 1, 50, ['supplier_id' => $taxable->product->supplier_id]);
        $exempt->update(['is_taxable' => false]);
        $this->order->recalculateBalance();

        $this->order->applyPromotion(promotionOn($taxable->product->supplier, state: fn ($factory) => $factory->taxesPaid()));

        expect($taxable->fresh()->unit_price)->toBeMoney(8697)
            ->and($exempt->fresh())->unit_price->toBeMoney(5000)->promotion_id->toBeNull()
            ->and($this->order->fresh()->total->cents())->toBeBetween(14999, 15001);
    });

    it('n\'applique qu\'une promotion par commande', function () {
        $line = regularLine($this->order, 1, 100);
        $this->order->applyPromotion(promotionOn($line->product));
        $other = promotionOn($line->product->supplier);

        expect($this->order->fresh()->applicablePromotions())->toBeEmpty()
            ->and(fn () => $this->order->fresh()->applyPromotion($other))->toThrow(DomainException::class, 'déjà appliquée');
    });

    it('ignore les promotions inactives, à venir ou terminées', function () {
        $line = regularLine($this->order, 1, 100);
        promotionOn($line->product, ['is_active' => false]);
        promotionOn($line->product, ['starts_on' => today()->addDay(), 'ends_on' => today()->addWeek()]);
        promotionOn($line->product, ['starts_on' => today()->subWeek(), 'ends_on' => today()->subDay()]);

        expect($this->order->applicablePromotions())->toBeEmpty();
    });

    it('retire la promotion et rétablit les prix réguliers', function () {
        $line = regularLine($this->order, 1, 100);
        $this->order->applyPromotion(promotionOn($line->product));

        $this->order->fresh()->removePromotion();

        expect($line->fresh())
            ->unit_price->toBeMoney(10000)
            ->regular_unit_price->toBeNull()
            ->promotion_id->toBeNull()
            ->and($this->order->fresh())
            ->promotion_id->toBeNull()
            ->total->toBeMoney(11498);
    });

    it('détache la ligne de la promotion quand son prix est changé à la main', function () {
        $line = regularLine($this->order, 1, 100);
        $this->order->applyPromotion(promotionOn($line->product));
        $line = $line->fresh();

        $this->order->updateLine($line, $line->quantity_reserved, $line->quantity_on_order, Money::parse(85), null);

        expect($line->fresh())->unit_price->toBeMoney(8500)->promotion_id->toBeNull()->regular_unit_price->toBeNull();
    });

    it('ajoute le même produit sur une nouvelle ligne plutôt que dans la ligne escomptée', function () {
        $line = regularLine($this->order, 2, 100);
        $this->order->applyPromotion(promotionOn($line->product));

        $added = $this->order->fresh()->addProduct($line->product->fresh(), 1);

        expect($added->id)->not->toBe($line->id)
            ->and($added->promotion_id)->toBeNull()
            ->and($line->fresh()->quantity)->toBe(2);
    });
});

describe('bouton Promotion à l\'étape du paiement', function () {
    it('est désactivé sans promotion en vigueur', function () {
        regularLine($this->order, 1, 100);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openPickupModal')
            ->call('continueToPayment')
            ->assertSee('Aucune promotion en vigueur pour ces produits.');
    });

    it('applique directement la seule promotion admissible et recalcule le montant à encaisser', function () {
        $line = regularLine($this->order, 1, 100);
        $promotion = promotionOn($line->product, ['percent' => 10]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openPickupModal')
            ->call('continueToPayment')
            ->assertSet('pickupRequiredCents', 11498)
            ->call('applyPromotion')
            ->assertSet('choosingPromotion', false)
            ->assertSet('pickupRequiredCents', 10348)
            ->assertSet('pickupPayments.0.amount', '103.48')
            ->assertSee($promotion->name);

        expect($this->order->fresh()->promotion_id)->toBe($promotion->id);
    });

    it('demande laquelle appliquer quand plusieurs promotions s\'appliquent', function () {
        $line = regularLine($this->order, 1, 100);
        $first = promotionOn($line->product, ['name' => 'Promo A', 'percent' => 10]);
        $second = promotionOn($line->product->supplier, ['name' => 'Promo B', 'percent' => 20]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openPickupModal')
            ->call('continueToPayment')
            ->call('applyPromotion')
            ->assertSet('choosingPromotion', true)
            ->assertSeeInOrder([$first->name, $second->name])
            ->call('applyPromotion', $second->id)
            ->assertSet('choosingPromotion', false)
            ->assertSet('pickupRequiredCents', 9198);

        expect($this->order->fresh()->promotion_id)->toBe($second->id);
    });

    it('retire la promotion appliquée', function () {
        $line = regularLine($this->order, 1, 100);
        $this->order->applyPromotion(promotionOn($line->product));

        Livewire::test(Show::class, ['order' => $this->order->fresh()])
            ->call('openPickupModal')
            ->call('continueToPayment')
            ->call('removePromotion')
            ->assertSet('pickupRequiredCents', 11498);

        expect($this->order->fresh()->promotion_id)->toBeNull();
    });
});

describe('gestion des promotions', function () {
    beforeEach(function () {
        $this->user->givePermissionTo(['promotions.view', 'promotions.create', 'promotions.edit']);
    });

    it('refuse la page sans la permission', function () {
        $this->actingAs(User::factory()->withRole('visiteur')->create())
            ->get(route('catalog.promotions'))
            ->assertForbidden();
    });

    it('crée un montant fixe qui vise un département et un produit saisi par son modèle', function () {
        $department = Department::factory()->create();
        $product = Product::factory()->create(['model' => 'WRF-555 SDF']);

        Livewire::test(Promotions::class)
            ->call('openCreate')
            ->set('name', 'Épargnez 100 $')
            ->set('type', 'fixed_amount')
            ->set('amount', '100,00')
            ->set('minimumQuantity', '2')
            ->set('targets', [['type' => 'department', 'value' => $department->id], ['type' => 'product', 'value' => 'wrf555sdf']])
            ->call('save')
            ->assertHasNoErrors();

        $promotion = Promotion::query()->sole();

        expect($promotion)
            ->type->toBe(PromotionType::FixedAmount)
            ->amount->toBeMoney(10000)
            ->minimum_quantity->toBe(2)
            ->percent->toBeNull()
            ->and($promotion->targets()->orderBy('id')->get()->map(fn ($target) => [$target->target_type, $target->target_id])->all())
            ->toBe([['department', $department->id], ['product', $product->id]]);
    });

    it('valide le rabais, la période et les produits visés', function () {
        Livewire::test(Promotions::class)
            ->call('openCreate')
            ->set('name', 'Sans rabais')
            ->set('percent', '')
            ->set('startsOn', today()->toDateString())
            ->set('endsOn', today()->subDay()->toDateString())
            ->set('targets', [])
            ->call('save')
            ->assertHasErrors(['percent' => 'required', 'endsOn' => 'after_or_equal', 'targets' => 'required']);

        expect(Promotion::query()->count())->toBe(0);
    });

    it('refuse un modèle introuvable', function () {
        Livewire::test(Promotions::class)
            ->call('openCreate')
            ->set('name', '10 %')
            ->set('percent', '10')
            ->set('targets', [['type' => 'product', 'value' => 'inexistant']])
            ->call('save')
            ->assertHasErrors(['targets.0.value']);

        expect(Promotion::query()->count())->toBe(0);
    });

    it('désactive une promotion plutôt que de la supprimer', function () {
        $promotion = promotionOn(Department::factory()->create());

        Livewire::test(Promotions::class)
            ->call('openEdit', $promotion->id)
            ->set('isActive', false)
            ->call('save')
            ->assertHasNoErrors();

        expect($promotion->fresh()->is_active)->toBeFalse()
            ->and($promotion->targets()->count())->toBe(1);
    });
});
