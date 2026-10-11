<?php

/*
|--------------------------------------------------------------------------
| Tests témoins des montants (issue #76, ADR 0008)
|--------------------------------------------------------------------------
|
| Montants exacts, calculés à la main, des calculs d'argent de l'application. Ils doivent rester identiques
| pendant tout le passage des montants en cents : seule la mise en place (arrange) peut changer, jamais les
| valeurs attendues. Les montants sont comparés en cents pour être indépendants du type (float ou Money).
|
*/

use App\Enums\SupplierType;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->actingAs(User::factory()->withRole('admin')->create());
});

/**
 * Montant en cents, qu'il soit encore un float (dollars) ou déjà un objet Money.
 */
function centsOf(mixed $amount): int
{
    return is_object($amount) && method_exists($amount, 'cents')
        ? $amount->cents()
        : (int) round((float) $amount * 100);
}

/**
 * Produit en stock ajouté à la commande, au prix vendant donné.
 */
function goldenLine(CustomerOrder $order, int $quantity, string $price, bool $isTaxable = true): void
{
    $line = stockedLine($order, $quantity, $quantity, (float) $price);

    if (! $isTaxable) {
        $line->update(['is_taxable' => false]);
        $order->recalculateBalance();
    }
}

describe('commande client (TPS 5 %, TVQ 9,975 %)', function () {
    beforeEach(function () {
        $this->order = CustomerOrder::factory()->create();
        goldenLine($this->order, 3, '19.99');
        goldenLine($this->order, 1, '10.00', isTaxable: false);
        $this->order->refresh();
    });

    it('calcule le sous-total, chaque taxe arrondie au cent et le total', function () {
        $taxes = $this->order->taxLines->pluck('amount', 'name');

        expect(centsOf($this->order->subtotal))->toBe(6997)
            ->and(centsOf($taxes['TPS']))->toBe(300)
            ->and(centsOf($taxes['TVQ']))->toBe(598)
            ->and(centsOf($this->order->total))->toBe(7895)
            ->and(centsOf($this->order->balance_due))->toBe(7895);
    });

    it('exige un dépôt de 30 % arrondi au cent supérieur à la demie', function () {
        expect(centsOf($this->order->amountRequiredFor([])))->toBe(2369);
    });

    it('arrondit un paiement comptant à 0,05 $ et rend la monnaie', function () {
        $this->order->pickUp([], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => Money::parse(23.69), 'tendered' => Money::parse(30)]]);

        $payment = $this->order->payments()->sole();

        expect(centsOf($payment->amount))->toBe(2369)
            ->and(centsOf($payment->rounding_adjustment))->toBe(1)
            ->and(centsOf($payment->cash_tendered))->toBe(3000)
            ->and(centsOf($payment->change_given))->toBe(630)
            ->and(centsOf($this->order->fresh()->balance_due))->toBe(5526);
    });
});

it('calcule les frais d\'annulation sur le prix et les taxe', function () {
    $order = CustomerOrder::factory()->create();
    $order->store->update(['cancellation_fee_percent' => 15]);
    $product = Product::factory()->create(['supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id]);
    $line = $order->addProduct($product, 2);
    $order->updateLine($line, 0, 2, Money::parse(33.33), null);
    $line->fresh()->supplierOrderLine->order->send();

    $fee = $order->fresh()->cancelLineWithFee($line->fresh());

    expect(centsOf($fee))->toBe(1000)
        ->and(centsOf($order->fresh()->total))->toBe(1150);
});

it('rembourse un article remis avec ses taxes', function () {
    $order = CustomerOrder::factory()->create();
    $line = pickedUpLine($order, 1, 19.99, CustomerPaymentMethod::cash());

    expect(centsOf($order->fresh()->refundableFor($line, 1)))->toBe(2298);
});

it('arrondit le prix de vente d\'un produit selon sa tranche', function (string $cost, float $multiplier, int $expected) {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product, 'base_multiplier' => $multiplier]);
    $product = Product::factory()->create(['supplier_id' => $supplier->id, 'cost' => Money::parse($cost)]);

    expect(centsOf($product->fresh()->selling_price))->toBe($expected);
})->with([
    'sous 20 $ : x,99' => ['7.00', 2, 1499],
    'sous 20 $ : x,99 au-dessus de la demie' => ['1.15', 2, 299],
    'de 20 à 100 $ : dollar supérieur' => ['12.34', 2.35, 2900],
    'tout juste sous 100 $' => ['33.33', 3, 10000],
    'plus de 100 $ : finit par 4 ou 9' => ['50.00', 2.5, 12900],
]);

it('calcule le total, l\'écart et l\'escompte d\'une facture fournisseur', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product, 'early_payment_discount_percent' => 2, 'early_payment_discount_days' => 10]);

    $invoice = SupplierInvoice::recordStandalone($supplier, [
        'invoice_number' => 'F-1001',
        'invoice_date' => '2026-10-01',
        'merchandise_total' => '12.99',
        'freight_fee' => '5.10',
        'customs_fee' => '1.25',
        'taxes' => '2.90',
        'invoice_total' => '22.25',
    ]);

    expect(centsOf($invoice->computed_total))->toBe(2224)
        ->and(centsOf($invoice->variance))->toBe(1)
        ->and(centsOf($invoice->discount_amount))->toBe(45);
});

/*
 * Écarts d'un cent : le float donnait 0,61 $ là où 0,615 $ doit s'arrondir à 0,62 $ (125 écarts trouvés sur 400 000
 * calculs de pourcentage). Ces tests échouaient tant que les montants étaient en float.
 */
describe('écarts d\'un cent dus au float', function () {
    it('arrondit un dépôt de 30 % à la demie supérieure', function () {
        $order = CustomerOrder::factory()->create();
        goldenLine($order, 1, '2.05', isTaxable: false);

        expect(centsOf($order->fresh()->amountRequiredFor([])))->toBe(62);
    });

    it('arrondit des frais d\'annulation de 15 % à la demie supérieure', function () {
        $order = CustomerOrder::factory()->create();
        $order->store->update(['cancellation_fee_percent' => 15]);
        $product = Product::factory()->create(['supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id]);
        $line = $order->addProduct($product);
        $order->updateLine($line, 0, 1, Money::parse(4.10), null);
        $line->fresh()->supplierOrderLine->order->send();

        expect(centsOf($order->fresh()->cancelLineWithFee($line->fresh())))->toBe(62);
    });
});
