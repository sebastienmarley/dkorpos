<?php

use App\Livewire\Schedules\Appointments;
use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-28 07:00:00'));

    $this->employee = User::factory()->create(['is_active' => true]);
    $this->actingAs($this->employee);

    $this->futureDate = '2026-10-13';
});

afterEach(fn () => Carbon::setTestNow());

function bookingComponent(User $employee, string $date, int $startMinute)
{
    return Livewire::test(Appointments::class)
        ->set('selectedUserId', $employee->id)
        ->call('openCell', $date, $startMinute);
}

it('redirige les invités vers la connexion', function () {
    auth()->logout();

    $this->get(route('schedules.appointments'))->assertRedirect(route('login'));
});

it('enregistre un rendez-vous de 30 minutes à une heure non ronde', function () {
    bookingComponent($this->employee, $this->futureDate, 9 * 60 + 30)
        ->set('title', 'Consultation')
        ->set('durationMinutes', 30)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('appointments', [
        'user_id' => $this->employee->id,
        'date' => $this->futureDate,
        'start_minute' => 570,
        'duration_minutes' => 30,
    ]);
});

it('refuse une durée qui chevauche le rendez-vous suivant', function () {
    Appointment::factory()->create([
        'user_id' => $this->employee->id,
        'date' => $this->futureDate,
        'start_minute' => 10 * 60,
        'duration_minutes' => 60,
    ]);

    bookingComponent($this->employee, $this->futureDate, 9 * 60)
        ->set('title', 'Long')
        ->set('durationMinutes', 120)
        ->call('save')
        ->assertHasErrors('durationMinutes');

    expect(Appointment::count())->toBe(1);
});

it('accepte un rendez-vous qui se termine exactement quand le suivant commence', function () {
    Appointment::factory()->create([
        'user_id' => $this->employee->id,
        'date' => $this->futureDate,
        'start_minute' => 10 * 60,
        'duration_minutes' => 60,
    ]);

    bookingComponent($this->employee, $this->futureDate, 9 * 60)
        ->set('title', 'Court')
        ->set('durationMinutes', 60)
        ->call('save')
        ->assertHasNoErrors();

    expect(Appointment::count())->toBe(2);
});

it('modifie un rendez-vous existant sans le compter comme chevauchement', function () {
    $appointment = Appointment::factory()->create([
        'user_id' => $this->employee->id,
        'date' => $this->futureDate,
        'start_minute' => 9 * 60,
        'duration_minutes' => 60,
    ]);

    bookingComponent($this->employee, $this->futureDate, 9 * 60)
        ->assertSet('editingAppointmentId', $appointment->id)
        ->set('durationMinutes', 90)
        ->call('save')
        ->assertHasNoErrors();

    expect($appointment->fresh()->duration_minutes)->toBe(90);
});

it('limite la durée à la fin de l\'horaire dans la semaine en cours', function () {
    Schedule::factory()->create([
        'user_id' => $this->employee->id,
        'date' => '2026-09-29',
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
    ]);

    bookingComponent($this->employee, '2026-09-29', 11 * 60)
        ->set('title', 'Trop long')
        ->set('durationMinutes', 90)
        ->call('save')
        ->assertHasErrors('durationMinutes');
});

it('ignore les cases hors de l\'horaire de l\'employé dans la semaine en cours', function () {
    Schedule::factory()->create([
        'user_id' => $this->employee->id,
        'date' => '2026-09-29',
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
    ]);

    bookingComponent($this->employee, '2026-09-29', 13 * 60)
        ->assertSet('showModal', false);
});

it('ignore les créneaux déjà passés aujourd\'hui', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-28 10:15:00'));

    Schedule::factory()->create([
        'user_id' => $this->employee->id,
        'date' => '2026-09-28',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
    ]);

    bookingComponent($this->employee, '2026-09-28', 9 * 60 + 30)->assertSet('showModal', false);
    bookingComponent($this->employee, '2026-09-28', 11 * 60)->assertSet('showModal', true);
});

it('supprime un rendez-vous', function () {
    $appointment = Appointment::factory()->create([
        'user_id' => $this->employee->id,
        'date' => $this->futureDate,
        'start_minute' => 9 * 60,
    ]);

    bookingComponent($this->employee, $this->futureDate, 9 * 60)->call('deleteAppointment');

    $this->assertModelMissing($appointment);
});

it('affiche les heures de début et de fin du rendez-vous', function () {
    Appointment::factory()->create([
        'user_id' => $this->employee->id,
        'date' => '2026-10-13',
        'start_minute' => 9 * 60 + 30,
        'duration_minutes' => 90,
        'title' => 'Coloration',
    ]);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->set('weekStart', '2026-10-11')
        ->assertSee('Coloration')
        ->assertSee('09:30')
        ->assertSee('11:00');
});

// ── Glisser-déposer ────────────────────────────────────────────────────────

function movableAppointment(User $employee, string $date, int $startMinute = 540, int $duration = 60): Appointment
{
    return Appointment::factory()->create([
        'user_id' => $employee->id,
        'date' => $date,
        'start_minute' => $startMinute,
        'duration_minutes' => $duration,
    ]);
}

it('déplace un rendez-vous vers un autre jour et une autre heure', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('updateAppointmentTime', $appointment->id, '2026-10-14', 14 * 60 + 30, 60, $appointment->updated_at->getTimestamp());

    expect($appointment->fresh())
        ->date->toBe('2026-10-14')
        ->start_minute->toBe(870)
        ->duration_minutes->toBe(60);
});

it('redimensionne un rendez-vous', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('updateAppointmentTime', $appointment->id, $this->futureDate, 540, 150, $appointment->updated_at->getTimestamp());

    expect($appointment->fresh()->duration_minutes)->toBe(150);
});

it('ignore un déplacement qui chevauche un autre rendez-vous', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);
    movableAppointment($this->employee, $this->futureDate, 11 * 60);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('updateAppointmentTime', $appointment->id, $this->futureDate, 540, 150, $appointment->updated_at->getTimestamp());

    expect($appointment->fresh()->duration_minutes)->toBe(60);
});

it('ignore un déplacement vers le passé ou hors de la grille', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);

    $component = Livewire::test(Appointments::class)->set('selectedUserId', $this->employee->id);

    $component->call('updateAppointmentTime', $appointment->id, '2026-09-20', 540, 60, $appointment->updated_at->getTimestamp());
    $component->call('updateAppointmentTime', $appointment->id, $this->futureDate, 5 * 60, 60, $appointment->updated_at->getTimestamp());
    $component->call('updateAppointmentTime', $appointment->id, $this->futureDate, 18 * 60 + 30, 120, $appointment->updated_at->getTimestamp());
    $component->call('updateAppointmentTime', $appointment->id, $this->futureDate, 545, 60, $appointment->updated_at->getTimestamp());

    expect($appointment->fresh())->date->toBe($this->futureDate)->start_minute->toBe(540);
});

it('ne modifie pas le rendez-vous d\'un autre employé', function () {
    $other = User::factory()->create(['is_active' => true]);
    $appointment = movableAppointment($other, $this->futureDate);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('updateAppointmentTime', $appointment->id, $this->futureDate, 600, 60, $appointment->updated_at->getTimestamp());

    expect($appointment->fresh()->start_minute)->toBe(540);
});

it('affiche un message quand le déplacement est refusé', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);
    movableAppointment($this->employee, $this->futureDate, 11 * 60);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('updateAppointmentTime', $appointment->id, $this->futureDate, 540, 150, $appointment->updated_at->getTimestamp())
        ->assertDispatched('toast-show');
});

// ── Double réservation ─────────────────────────────────────────────────────

it('refuse une plage prise par un autre usager après l\'ouverture du formulaire', function () {
    $component = bookingComponent($this->employee, $this->futureDate, 9 * 60)
        ->set('title', 'Second usager')
        ->set('durationMinutes', 60);

    movableAppointment($this->employee, $this->futureDate, 9 * 60);

    $component->call('save')->assertHasErrors('durationMinutes');

    expect(Appointment::count())->toBe(1);
});

it('refuse de commencer au milieu d\'un rendez-vous existant', function () {
    movableAppointment($this->employee, $this->futureDate, 9 * 60, 120);

    bookingComponent($this->employee, $this->futureDate, 10 * 60)
        ->set('title', 'Dedans')
        ->set('durationMinutes', 30)
        ->call('save')
        ->assertHasErrors('durationMinutes');

    expect(Appointment::count())->toBe(1);
});

it('permet le même créneau à deux employés différents', function () {
    $other = User::factory()->create(['is_active' => true]);
    movableAppointment($other, $this->futureDate, 9 * 60);

    bookingComponent($this->employee, $this->futureDate, 9 * 60)
        ->set('title', 'Autre employé')
        ->call('save')
        ->assertHasNoErrors();

    expect(Appointment::count())->toBe(2);
});

it('refuse un déplacement vers une plage occupée par un rendez-vous qui la recouvre', function () {
    movableAppointment($this->employee, $this->futureDate, 9 * 60, 120);
    $appointment = movableAppointment($this->employee, $this->futureDate, 13 * 60);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('updateAppointmentTime', $appointment->id, $this->futureDate, 10 * 60, 60, $appointment->updated_at->getTimestamp());

    expect($appointment->fresh()->start_minute)->toBe(13 * 60);
});

// ── Auteur et modifications concurrentes ───────────────────────────────────

it('enregistre l\'auteur de la création puis celui de la dernière modification', function () {
    bookingComponent($this->employee, $this->futureDate, 9 * 60)
        ->set('title', 'Initial')
        ->call('save');

    $appointment = Appointment::firstOrFail();
    expect($appointment->created_by)->toBe($this->employee->id)
        ->and($appointment->last_updated_by)->toBe($this->employee->id);

    $editor = User::factory()->create(['is_active' => true]);
    $this->actingAs($editor);

    bookingComponent($this->employee, $this->futureDate, 9 * 60)
        ->set('title', 'Modifié')
        ->call('save')
        ->assertHasNoErrors();

    expect($appointment->fresh())
        ->created_by->toBe($this->employee->id)
        ->last_updated_by->toBe($editor->id);
});

it('refuse d\'enregistrer un rendez-vous modifié par quelqu\'un d\'autre entre-temps', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);

    $component = bookingComponent($this->employee, $this->futureDate, 540)->set('title', 'Ma version');

    $appointment->forceFill(['title' => 'Version de l\'autre', 'updated_at' => now()->addMinute()])->saveQuietly();

    $component->call('save')->assertHasErrors('title');

    expect($appointment->fresh()->title)->toBe('Version de l\'autre');
});

it('refuse un déplacement fondé sur une version périmée du rendez-vous', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);
    $staleVersion = $appointment->updated_at->getTimestamp();

    $appointment->forceFill(['updated_at' => now()->addMinute()])->saveQuietly();

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('updateAppointmentTime', $appointment->id, $this->futureDate, 600, 60, $staleVersion)
        ->assertDispatched('toast-show');

    expect($appointment->fresh()->start_minute)->toBe(540);
});

it('affiche qui a créé et modifié le rendez-vous dans la fenêtre d\'édition', function () {
    $author = User::factory()->create(['firstname' => 'Alice', 'lastname' => 'Auteur']);
    movableAppointment($this->employee, $this->futureDate)->forceFill(['created_by' => $author->id, 'last_updated_by' => $author->id])->saveQuietly();

    bookingComponent($this->employee, $this->futureDate, 540)->assertSee('Créé par Alice Auteur');
});

// ── Rendez-vous supprimé par un autre usager ───────────────────────────────

it('ferme la fenêtre avec un message si le rendez-vous a été supprimé pendant l\'édition', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);

    $component = bookingComponent($this->employee, $this->futureDate, 540)->set('title', 'Trop tard');

    $appointment->delete();

    $component->call('save')
        ->assertDispatched('toast-show')
        ->assertSet('showModal', false);

    expect(Appointment::count())->toBe(0);
});

it('affiche un message si le rendez-vous déplacé a été supprimé entre-temps', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);
    $version = $appointment->updated_at->getTimestamp();

    $appointment->delete();

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('updateAppointmentTime', $appointment->id, $this->futureDate, 600, 60, $version)
        ->assertDispatched('toast-show');

    expect(Appointment::count())->toBe(0);
});

it('ne plante pas en supprimant un rendez-vous déjà supprimé', function () {
    $appointment = movableAppointment($this->employee, $this->futureDate);

    $component = bookingComponent($this->employee, $this->futureDate, 540);

    $appointment->delete();

    $component->call('deleteAppointment')->assertSet('showModal', false);
});
