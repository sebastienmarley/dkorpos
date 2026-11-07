<?php

use App\Livewire\CustomerOrders\Show;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreTaxRegistration;
use App\Models\User;
use App\Notifications\Customers\OrderSummaryNotice;
use App\Support\Money;
use Database\Seeders\TaxSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(TaxSeeder::class);
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->store = Store::factory()->create(['name' => 'Succursale Rimouski', 'address_civic' => '25', 'address_street' => 'rue Saint-Germain', 'address_city' => 'Rimouski']);
    StoreTaxRegistration::factory()->create(['store_id' => $this->store->id, 'tax_name' => 'TPS', 'number' => '123456789RT0001']);
    $this->user = User::factory()->withRole('visiteur')->create(['store_id' => $this->store->id]);
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()
        ->for(customer::factory()->state(['firstname' => 'Marguerite', 'lastname' => 'Tremblay', 'email' => 'marguerite@client.test', 'address_civic' => '8', 'address_street' => 'rue des Érables']))
        ->create(['store_id' => $this->store->id]);
    $this->order->addProduct(Product::factory()->create(['model' => 'SOFA-88']), 2, Money::parse(100));
});

it('imprime la commande avec le magasin vendeur, le client, les taxes et le solde', function () {
    $this->get(route('customer-orders.print', $this->order))
        ->assertOk()
        ->assertSeeInOrder(['Succursale Rimouski', 'rue Saint-Germain', 'Client', 'Marguerite Tremblay', 'rue des Érables'])
        ->assertSeeInOrder(['SOFA-88', '200.00', 'Sous-total', 'TPS', '123456789RT0001', 'Total', 'Solde à payer'])
        ->assertSee('Imprimer');
});

it('refuse d\'imprimer la commande d\'un autre magasin', function () {
    $this->actingAs(tap(User::factory()->withRole('visiteur')->create(['store_id' => Store::factory()->create()->id]))->givePermissionTo('customer_orders.view'));

    $this->get(route('customer-orders.print', $this->order))->assertForbidden();
});

it('envoie la commande au courriel du client', function () {
    Notification::fake();

    Livewire::test(Show::class, ['order' => $this->order])->call('emailToCustomer');

    Notification::assertSentTo($this->order->customer, OrderSummaryNotice::class, function (OrderSummaryNotice $notice, array $channels, customer $notifiable): bool {
        $mail = $notice->toMail($notifiable);
        $html = $mail->render();

        return $mail->hasTo('marguerite@client.test')
            && str_contains($html, 'SOFA-88')
            && str_contains($html, '123456789RT0001')
            && ! str_contains($html, 'window.print()');
    });
});

it('n\'envoie rien à un client sans courriel', function () {
    Notification::fake();
    $this->order->customer->update(['email' => null]);

    expect($this->order->fresh()->emailToCustomer())->toBeFalse();
    Notification::assertNothingSent();
});
