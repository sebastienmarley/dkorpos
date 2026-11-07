<?php

use App\Actions\NotifyCustomer;
use App\Enums\CustomerNoticeStatus;
use App\Enums\CustomerNoticeType;
use App\Livewire\CustomerForm;
use App\Livewire\CustomerOrders\Show;
use App\Mail\CustomerOrderSummary;
use App\Models\customer;
use App\Models\CustomerNotice;
use App\Models\CustomerOrder;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Customers\OrderSummaryNotice;
use Database\Seeders\TaxSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(TaxSeeder::class);
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->store = Store::factory()->create(['name' => 'Succursale Rimouski', 'email' => 'rimouski@magasin.test']);
    $this->user = User::factory()->withRole('visiteur')->create(['store_id' => $this->store->id]);
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit', 'customers.create', 'customers.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()
        ->for(customer::factory()->state(['email' => 'marguerite@client.test']))
        ->create(['store_id' => $this->store->id]);
});

it('inscrit à l\'historique la commande envoyée à la main au client', function () {
    Notification::fake();

    Livewire::test(Show::class, ['order' => $this->order])
        ->call('emailToCustomer')
        ->assertSee('Avis au client')
        ->assertSee('Commande envoyée');

    Notification::assertSentTo($this->order->customer, OrderSummaryNotice::class);

    $notice = $this->order->notices()->sole();
    expect($notice->type)->toBe(CustomerNoticeType::OrderSummary)
        ->and($notice->status)->toBe(CustomerNoticeStatus::Sent)
        ->and($notice->recipient)->toBe('marguerite@client.test')
        ->and($notice->sent_at)->not->toBeNull()
        ->and($notice->created_by)->toBe($this->user->id);
});

it('n\'envoie rien à un client sans courriel et demande de l\'appeler', function () {
    Notification::fake();
    $this->order->customer->update(['email' => null]);

    app(NotifyCustomer::class)->handle($this->order->customer, CustomerNoticeType::OrderSummary, $this->order);

    Notification::assertNothingSent();
    expect($this->order->notices()->sole()->status)->toBe(CustomerNoticeStatus::NoEmail);

    Livewire::test(Show::class, ['order' => $this->order->fresh()])
        ->assertSee('Aucun avis envoyé : aviser le client par téléphone.');
});

it('envoie quand même la commande à la main à un client qui refuse les avis automatiques', function () {
    Notification::fake();
    $this->order->customer->update(['notifications_opt_out' => true]);

    expect($this->order->fresh()->emailToCustomer())->toBeTrue();
    Notification::assertSentTo($this->order->customer, OrderSummaryNotice::class);
});

it('n\'envoie qu\'un avis par événement', function () {
    Notification::fake();
    $notifier = app(NotifyCustomer::class);

    $first = $notifier->handle($this->order->customer, CustomerNoticeType::OrderSummary, $this->order, 'test-event:1');
    $second = $notifier->handle($this->order->customer, CustomerNoticeType::OrderSummary, $this->order, 'test-event:1');

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and(CustomerNotice::count())->toBe(1);
    Notification::assertSentToTimes($this->order->customer, OrderSummaryNotice::class, 1);
});

it('inscrit un avis échoué quand l\'envoi lance une erreur', function () {
    $this->mock(Dispatcher::class)->shouldReceive('sendNow')->andThrow(new RuntimeException('SMTP indisponible'));

    expect($this->order->emailToCustomer())->toBeFalse();

    $notice = $this->order->notices()->sole();
    expect($notice->status)->toBe(CustomerNoticeStatus::Failed)
        ->and($notice->failed_at)->not->toBeNull()
        ->and($notice->sent_at)->toBeNull();
});

it('renvoie un avis échoué au courriel actuel du client', function () {
    Notification::fake();
    $notice = CustomerNotice::factory()->failed()->create(['customer_order_id' => $this->order->id, 'recipient' => 'ancien@client.test']);

    Livewire::test(Show::class, ['order' => $this->order])
        ->assertSee('Renvoyer')
        ->call('resendNotice', $notice->id);

    Notification::assertSentTo($this->order->customer, OrderSummaryNotice::class);
    expect($notice->refresh()->status)->toBe(CustomerNoticeStatus::Sent)
        ->and($notice->recipient)->toBe('marguerite@client.test')
        ->and($notice->failed_at)->toBeNull()
        ->and(CustomerNotice::count())->toBe(1);
});

it('refuse de renvoyer un avis déjà envoyé', function () {
    Notification::fake();
    $notice = CustomerNotice::factory()->create(['customer_order_id' => $this->order->id]);

    Livewire::test(Show::class, ['order' => $this->order])
        ->assertDontSee('Renvoyer')
        ->call('resendNotice', $notice->id)
        ->assertForbidden();

    Notification::assertNothingSent();
});

it('dirige la réponse du client vers le courriel du magasin', function () {
    $mail = (new OrderSummaryNotice($this->order))->toMail($this->order->customer);

    expect($mail)->toBeInstanceOf(CustomerOrderSummary::class)
        ->and($mail->hasTo('marguerite@client.test'))->toBeTrue()
        ->and($mail->hasReplyTo('rimouski@magasin.test'))->toBeTrue();
});

it('sauvegarde le refus des avis automatiques sur la fiche du client', function () {
    $customer = $this->order->customer;

    Livewire::test(CustomerForm::class)
        ->call('openEdit', $customer->id)
        ->assertSet('notificationsOptOut', false)
        ->set('notificationsOptOut', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($customer->refresh()->notifications_opt_out)->toBeTrue();
});
