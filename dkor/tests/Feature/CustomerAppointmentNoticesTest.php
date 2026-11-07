<?php

use App\Enums\CustomerNoticeStatus;
use App\Enums\CustomerNoticeType;
use App\Livewire\Schedules\Appointments;
use App\Models\Appointment;
use App\Models\customer;
use App\Models\CustomerNotice;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Customers\AppointmentNotice;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * Avis au client sur son rendez-vous et rappel de la veille (issue #138).
 */

beforeEach(function () {
    $this->travelTo('2026-09-28 07:00');
    Notification::fake();

    $this->store = Store::factory()->create(['name' => 'Succursale Rimouski', 'address_civic' => '25', 'address_street' => 'rue Saint-Germain', 'address_city' => 'Rimouski', 'email' => 'rimouski@magasin.test']);
    $this->employee = User::factory()->create(['is_active' => true, 'store_id' => $this->store->id]);
    $this->actingAs($this->employee);
    $this->customer = customer::factory()->create(['firstname' => 'Marguerite', 'email' => 'marguerite@client.test']);
});

function customerAppointment(customer $customer, User $employee, array $attributes = []): Appointment
{
    return Appointment::factory()->create(['user_id' => $employee->id, 'customer_id' => $customer->id, 'date' => '2026-10-13', 'start_minute' => 9 * 60, ...$attributes]);
}

/** Courriels envoyés au client pour ce type d'avis de rendez-vous (sujet et lignes). */
function appointmentMails(customer $customer, CustomerNoticeType $type): array
{
    return Notification::sent($customer, AppointmentNotice::class)
        ->filter(fn (AppointmentNotice $notice): bool => $notice->type === $type)
        ->map(function (AppointmentNotice $notice) use ($customer): string {
            $mail = $notice->toMail($customer);

            return $mail->subject."\n".implode("\n", $mail->introLines);
        })
        ->values()
        ->all();
}

it('confirme au client la date, l\'heure et l\'adresse de son rendez-vous', function () {
    $appointment = customerAppointment($this->customer, $this->employee);

    $mails = appointmentMails($this->customer, CustomerNoticeType::AppointmentBooked);
    expect($mails)->toHaveCount(1)
        ->and($mails[0])->toContain('13 octobre 2026 à 09:00')
        ->toContain('Succursale Rimouski')
        ->toContain('25 rue Saint-Germain');

    $notice = CustomerNotice::sole();
    expect($notice->status)->toBe(CustomerNoticeStatus::Sent)
        ->and($notice->subject->is($appointment))->toBeTrue();
});

it('avise le client quand la date ou l\'heure de son rendez-vous change', function () {
    $appointment = customerAppointment($this->customer, $this->employee);

    $appointment->update(['start_minute' => 14 * 60]);
    $afterTimeChange = appointmentMails($this->customer, CustomerNoticeType::AppointmentMoved);

    $this->travel(1)->minute();
    $appointment->update(['date' => '2026-10-14']);

    expect($afterTimeChange)->toHaveCount(1)
        ->and($afterTimeChange[0])->toContain('13 octobre 2026 à 14:00')
        ->and(appointmentMails($this->customer, CustomerNoticeType::AppointmentMoved))->toHaveCount(2)
        ->and(CustomerNotice::query()->where('type', CustomerNoticeType::AppointmentMoved)->count())->toBe(2);
});

it('n\'avise pas le client d\'un changement de titre, de notes ou d\'employé', function () {
    $appointment = customerAppointment($this->customer, $this->employee);

    $appointment->update(['title' => 'Autre titre', 'notes' => 'Apporter les échantillons', 'user_id' => User::factory()->create()->id]);

    expect(CustomerNotice::count())->toBe(1)
        ->and(appointmentMails($this->customer, CustomerNoticeType::AppointmentMoved))->toBe([]);
});

it('avise le client de l\'annulation de son rendez-vous supprimé à l\'agenda', function () {
    customerAppointment($this->customer, $this->employee);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('openCell', '2026-10-13', 9 * 60)
        ->call('deleteAppointment');

    $mails = appointmentMails($this->customer, CustomerNoticeType::AppointmentCancelled);
    expect(Appointment::count())->toBe(0)
        ->and($mails)->toHaveCount(1)
        ->and($mails[0])->toContain('13 octobre 2026 à 09:00');
    expect(CustomerNotice::query()->where('type', CustomerNoticeType::AppointmentCancelled)->sole()->isResendable())->toBeFalse();
});

it('n\'envoie rien pour un rendez-vous sans client ou déjà passé', function () {
    Appointment::factory()->create(['user_id' => $this->employee->id, 'customer_id' => null, 'date' => '2026-10-13']);
    $past = customerAppointment($this->customer, $this->employee, ['date' => '2026-10-13']);
    $this->travelTo('2026-10-14 08:00');
    Notification::fake();

    $past->delete();

    Notification::assertNothingSent();
});

it('rappelle la veille, une seule fois, les rendez-vous du lendemain', function () {
    $tomorrow = customerAppointment($this->customer, $this->employee, ['date' => '2026-09-29', 'start_minute' => 10 * 60]);
    customerAppointment(customer::factory()->create(['email' => 'autre@client.test']), $this->employee, ['date' => '2026-09-30']);

    $this->artisan('appointments:send-reminders')->assertSuccessful();
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    $mails = appointmentMails($this->customer, CustomerNoticeType::AppointmentReminder);
    expect($mails)->toHaveCount(1)
        ->and($mails[0])->toContain('demain')
        ->toContain('29 septembre 2026 à 10:00')
        ->and(CustomerNotice::query()->where('type', CustomerNoticeType::AppointmentReminder)->count())->toBe(1)
        ->and(CustomerNotice::query()->where('type', CustomerNoticeType::AppointmentReminder)->sole()->subject->is($tomorrow))->toBeTrue();
});

it('n\'envoie aucun avis de rendez-vous au client qui les refuse', function () {
    $this->customer->update(['notifications_opt_out' => true]);

    customerAppointment($this->customer, $this->employee);

    Notification::assertNothingSent();
    expect(CustomerNotice::sole()->status)->toBe(CustomerNoticeStatus::OptedOut);
});

it('dirige la réponse du client vers le courriel du magasin de l\'employé', function () {
    $appointment = customerAppointment($this->customer, $this->employee);

    $mail = (new AppointmentNotice($appointment, CustomerNoticeType::AppointmentBooked))->toMail($this->customer);

    expect($mail->replyTo)->toBe([['rimouski@magasin.test', 'Succursale Rimouski']]);
});
