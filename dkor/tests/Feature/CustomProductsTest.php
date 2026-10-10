<?php

use App\Enums\CustomerOrderLineStatus;
use App\Livewire\CustomerOrders\Show;
use App\Livewire\Stores\Show as StoreShow;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->order->store->update(['custom_deposit_percent' => 50]);
    $this->order->refresh();
    $this->template = Product::factory()->create(['model' => 'SOFA-SUR-MESURE', 'is_custom' => true, 'cost' => 1]);
});

it('vend un article sur mesure : toujours commandé, avec spécifications, coût soumis et prix saisi', function () {
    InventoryStock::factory()->create(['product_id' => $this->template->id, 'quantity_in_stock' => 5, 'quantity_reserved' => 0]);

    $line = $this->order->addCustomProduct($this->template, 1, '84 po, tissu Lin gris, pattes noires', 900, 1899.99, 'S-4521');

    $supplierLine = SupplierOrderLine::sole();

    expect($line)
        ->is_custom->toBeTrue()
        ->status->toBe(CustomerOrderLineStatus::OnOrder)
        ->quantity_reserved->toBe(0)
        ->quantity_on_order->toBe(1)
        ->unit_price->toBe(1899.99)
        ->unit_cost->toBe(900.0)
        ->quote_number->toBe('S-4521')
        ->and($supplierLine)
        ->product_id->toBe($this->template->id)
        ->unit_cost->toBe(900.0)
        ->description->toBe('84 po, tissu Lin gris, pattes noires (soumission S-4521)')
        ->label->toContain('tissu Lin gris')
        ->and($this->template->inventoryStock()->sole()->quantity_in_stock)->toBe(5);
});

it('crée une ligne distincte pour chaque article sur mesure', function () {
    $this->order->addCustomProduct($this->template, 1, 'Gris', 900, 1800);
    $this->order->addCustomProduct($this->template, 1, 'Bleu', 950, 1900);

    expect($this->order->lines()->count())->toBe(2)
        ->and(SupplierOrderLine::count())->toBe(2);
});

it('refuse d\'ajouter un gabarit sur mesure comme un produit ordinaire', function () {
    expect(fn () => $this->order->addProduct($this->template))->toThrow(DomainException::class)
        ->and(fn () => $this->order->addCustomProduct(Product::factory()->create(), 1, 'x', 1, 1))->toThrow(DomainException::class)
        ->and(fn () => $this->order->addCustomProduct($this->template, 1, '  ', 1, 1))->toThrow(DomainException::class);
});

it('modifie un article sur mesure non envoyé et la ligne fournisseur suit', function () {
    $line = $this->order->addCustomProduct($this->template, 1, 'Gris', 900, 1800);

    $this->order->updateCustomLine($line, 2, 'Gris pâle', 925, 1850, 'S-9', 'Livrer en avril');

    expect($line->fresh())->quantity->toBe(2)->unit_cost->toBe(925.0)->unit_price->toBe(1850.0)->note->toBe('Livrer en avril')
        ->and(SupplierOrderLine::sole())
        ->quantity->toBe(2)
        ->unit_cost->toBe(925.0)
        ->description->toBe('Gris pâle (soumission S-9)');
});

it('ne modifie plus un article sur mesure envoyé, sauf la note', function () {
    $line = $this->order->addCustomProduct($this->template, 1, 'Gris', 900, 1800);
    SupplierOrder::sole()->send();

    expect(fn () => $this->order->updateCustomLine($line->fresh(), 1, 'Bleu', 900, 1800, null, null))->toThrow(DomainException::class);

    $this->order->updateCustomLine($line->fresh(), 1, 'Gris', 900, 1800, null, 'Appeler le client');

    expect($line->fresh()->note)->toBe('Appeler le client');
});

it('réserve au client l\'article sur mesure reçu', function () {
    $line = $this->order->addCustomProduct($this->template, 1, 'Gris', 900, 1800);
    $supplierOrder = SupplierOrder::sole();
    $supplierOrder->send();

    $supplierOrder->fresh()->receive([SupplierOrderLine::sole()->id => ['quantity' => 1]]);

    expect($line->fresh())->status->toBe(CustomerOrderLineStatus::Received)->quantity_reserved->toBe(1);
});

it('exige le dépôt sur mesure du magasin sur les articles sur mesure', function () {
    $this->order->addCustomProduct($this->template, 1, 'Gris', 500, 1000);
    stockedLine($this->order, 1, 1, 100);

    $order = $this->order->fresh();
    $expected = round($order->taxesFor(1000)['total'] * 0.5 + $order->taxesFor(100)['total'] * 0.3, 2);

    expect($order->amountRequiredFor([]))->toBe($expected);
});

it('garde les caractéristiques sur mesure quand la ligne est séparée au ramassage', function () {
    $line = $this->order->addCustomProduct($this->template, 2, 'Gris', 500, 1000);
    $supplierOrder = SupplierOrder::sole();
    $supplierOrder->send();
    $supplierOrder->fresh()->receive([SupplierOrderLine::sole()->id => ['quantity' => 2]]);

    $this->order->pickUp([$line->id => 1], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);

    expect($this->order->lines()->where('status', CustomerOrderLineStatus::PickedUp)->sole())
        ->is_custom->toBeTrue()
        ->description->toBe('Gris')
        ->unit_cost->toBe(500.0);
});

it('recommande un article sur mesure défectueux avec les mêmes spécifications', function () {
    $line = $this->order->addCustomProduct($this->template, 1, 'Gris', 500, 1000);
    $supplierOrder = SupplierOrder::sole();
    $supplierOrder->send();
    $supplierOrder->fresh()->receive([SupplierOrderLine::sole()->id => ['quantity' => 1]]);
    $this->order->pickUp([$line->id => 1], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);

    $this->order->returnDefective($line->fresh(), 1, 'Couture défaite', replace: true);

    expect($this->order->lines()->where('status', CustomerOrderLineStatus::OnOrder)->sole())
        ->is_custom->toBeTrue()
        ->description->toBe('Gris')
        ->unit_cost->toBe(500.0)
        ->unit_price->toBe(1000.0);
});

describe('écran', function () {
    it('ouvre le modal sur mesure quand on choisit un gabarit et commande l\'article', function () {
        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openProductModal')
            ->set('productSupplierId', (string) $this->template->supplier_id)
            ->set('productSearch', 'SOFA')
            ->assertSee('Sur mesure')
            ->call('addProduct', $this->template->id)
            ->assertSet('showProductModal', false)
            ->assertSet('showCustomModal', true)
            ->call('addCustom')
            ->assertHasErrors(['customSpecifications', 'customCost', 'customPrice'])
            ->set('customSpecifications', '84 po, Lin gris')
            ->set('customCost', '900')
            ->set('customPrice', '1899,99')
            ->set('customQuoteNumber', 'S-1')
            ->call('addCustom')
            ->assertHasNoErrors()
            ->assertSet('showCustomModal', false);

        expect($this->order->lines()->sole())->is_custom->toBeTrue()->unit_price->toBe(1899.99);
    });

    it('exige l\'autorisation de la gestion pour reprendre un article sur mesure en retour', function () {
        $line = $this->order->addCustomProduct($this->template, 1, 'Gris', 500, 1000);
        $supplierOrder = SupplierOrder::sole();
        $supplierOrder->send();
        $supplierOrder->fresh()->receive([SupplierOrderLine::sole()->id => ['quantity' => 1]]);
        $this->order->pickUp([$line->id => 1], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->assertSee('Retour (autorisation requise)')
            ->call('openReturnModal', $line->id)
            ->assertForbidden();

        $this->user->givePermissionTo('customer_orders.return_custom');

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openReturnModal', $line->id)
            ->assertSet('showReturnModal', true)
            ->call('continueReturn')
            ->assertHasNoErrors();

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Returned);
    });

    it('sauvegarde le dépôt sur mesure du magasin', function () {
        $this->actingAs(User::factory()->withRole('admin')->create());
        $store = Store::factory()->create();

        Livewire::test(StoreShow::class, ['store' => $store])
            ->assertSet('customDepositPercent', '50')
            ->set('customDepositPercent', '60')
            ->call('saveAccounting')
            ->assertHasNoErrors();

        expect($store->fresh()->custom_deposit_percent)->toBe(60.0);
    });
});
