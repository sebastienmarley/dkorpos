<?php

/*
|--------------------------------------------------------------------------
| Rendez-vous pris hors de l'horaire publié (issue #16)
|--------------------------------------------------------------------------
|
| Lundi 28 septembre 2026 : la semaine en cours finit le samedi 3 octobre. Sans quart publié, un rendez-vous
| peut être pris jusqu'au samedi 17 octobre. Le gestionnaire de l'horaire voit les plages à couvrir.
|
*/

use App\Enums\ScheduleStatus;
use App\Enums\ScheduleType;
use App\Livewire\Schedules\Appointments;
use App\Livewire\Schedules\ScheduleEdit;
use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\User;
use App\Support\AppointmentBooking;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-28 07:00:00'));

    $this->admin = User::factory()->withRole('admin')->create();
    $this->employee = User::factory()->create(['is_active' => true]);
    $this->actingAs($this->admin);
});

afterEach(fn () => Carbon::setTestNow());

function bookAt(User $employee, string $date, int $startMinute, int $duration = 30)
{
    return Livewire::test(Appointments::class)
        ->set('selectedUserId', $employee->id)
        ->call('openCell', $date, $startMinute)
        ->set('title', 'Mesure de lunettes')
        ->set('durationMinutes', $duration)
        ->call('save');
}

function appointmentFor(User $employee, string $date, int $startMinute = 10 * 60, int $duration = 60): Appointment
{
    return Appointment::factory()->create([
        'user_id' => $employee->id,
        'date' => $date,
        'start_minute' => $startMinute,
        'duration_minutes' => $duration,
        'title' => 'Ajustement de monture',
    ]);
}

function nextWeekEditor()
{
    return Livewire::test(ScheduleEdit::class)->set('weekStart', '2026-10-04');
}

// ── Prise de rendez-vous ───────────────────────────────────────────────────

it('accepte un rendez-vous sans quart publié jusqu\'au dernier jour de l\'horizon', function () {
    bookAt($this->employee, '2026-10-17', 10 * 60)->assertHasNoErrors();

    expect(Appointment::query()->where('user_id', $this->employee->id)->where('date', '2026-10-17')->exists())->toBeTrue();
});

it('refuse un rendez-vous sans quart publié au-delà de l\'horizon', function () {
    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('openCell', '2026-10-18', 10 * 60)
        ->assertSet('showModal', false);
});

it('accepte un rendez-vous au-delà de l\'horizon pendant un quart publié', function () {
    Schedule::factory()->forDate('2026-10-20')->published()->create(['user_id' => $this->employee->id, 'start_time' => '09:00', 'end_time' => '12:00']);

    bookAt($this->employee, '2026-10-20', 10 * 60)->assertHasNoErrors();

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('openCell', '2026-10-20', 13 * 60)
        ->assertSet('showModal', false);
});

it('garde le rendez-vous dans le quart publié d\'une semaine future', function () {
    Schedule::factory()->forDate('2026-10-06')->published()->create(['user_id' => $this->employee->id, 'start_time' => '09:00', 'end_time' => '12:00']);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('openCell', '2026-10-06', 13 * 60)
        ->assertSet('showModal', false);

    bookAt($this->employee, '2026-10-06', 11 * 60, 90)->assertHasErrors('durationMinutes');
});

it('ne permet toujours pas de rendez-vous sans quart publié dans la semaine en cours', function () {
    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->call('openCell', '2026-09-30', 10 * 60)
        ->assertSet('showModal', false);
});

it('marque « Quart à confirmer » un rendez-vous sans quart publié', function () {
    appointmentFor($this->employee, '2026-10-13');

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->set('weekStart', '2026-10-11')
        ->assertSee('⚠ Quart à confirmer');

    Schedule::factory()->forDate('2026-10-13')->published()->create(['user_id' => $this->employee->id, 'start_time' => '09:00', 'end_time' => '17:00']);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->set('weekStart', '2026-10-11')
        ->assertDontSee('⚠ Quart à confirmer');
});

// ── Avis au gestionnaire de l'horaire ──────────────────────────────────────

it('liste les rendez-vous à couvrir de la semaine avec leur motif', function () {
    appointmentFor($this->employee, '2026-10-06');
    $outside = User::factory()->create(['is_active' => true]);
    Schedule::factory()->forDate('2026-10-07')->published()->create(['user_id' => $outside->id, 'start_time' => '09:00', 'end_time' => '12:00']);
    appointmentFor($outside, '2026-10-07', 11 * 60 + 30, 60);

    nextWeekEditor()
        ->assertSee('2 rendez-vous à couvrir cette semaine')
        ->assertSee('Aucun quart')
        ->assertSee('Hors du quart')
        ->assertSee('Quart requis');
});

it('ne signale plus un rendez-vous couvert par un quart publié', function () {
    appointmentFor($this->employee, '2026-10-06');
    Schedule::factory()->forDate('2026-10-06')->published()->create(['user_id' => $this->employee->id, 'start_time' => '09:00', 'end_time' => '17:00']);

    nextWeekEditor()->assertDontSee('à couvrir cette semaine');
});

it('compte les rendez-vous à couvrir jusqu\'à la fin de l\'horizon', function () {
    appointmentFor($this->employee, '2026-10-06');
    appointmentFor($this->employee, '2026-10-17');
    appointmentFor($this->employee, '2026-10-24');
    appointmentFor($this->employee, '2026-09-25');

    Schedule::factory()->forDate('2026-10-08')->published()->create(['user_id' => $this->employee->id, 'start_time' => '09:00', 'end_time' => '17:00']);
    appointmentFor($this->employee, '2026-10-08');

    expect(AppointmentBooking::uncoveredCount())->toBe(2);
});

// ── Quarts et publication ──────────────────────────────────────────────────

it('accepte un quart en brouillon qui couvre les rendez-vous du jour', function () {
    $this->employee->update(['first_day' => '2026-01-01']);
    appointmentFor($this->employee, '2026-10-06');

    nextWeekEditor()
        ->call('openCell', $this->employee->id, '2026-10-06')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::query()->where('user_id', $this->employee->id)->firstOrFail()->status)->toBe(ScheduleStatus::Draft);
});

it('refuse un quart en brouillon qui laisse un rendez-vous hors du quart', function () {
    $this->employee->update(['first_day' => '2026-01-01']);
    appointmentFor($this->employee, '2026-10-06', 15 * 60);

    nextWeekEditor()
        ->call('openCell', $this->employee->id, '2026-10-06')
        ->set('startTime', '09:00')
        ->set('endTime', '12:00')
        ->call('save')
        ->assertHasErrors('editingDate');

    expect(Schedule::count())->toBe(0);
});

it('refuse de publier une semaine qui laisse un rendez-vous sans quart', function () {
    appointmentFor($this->employee, '2026-10-06');
    Schedule::factory()->forDate('2026-10-07')->create(['user_id' => $this->employee->id]);

    nextWeekEditor()
        ->call('publishWeek')
        ->assertDispatched('toast-show');

    expect(Schedule::firstOrFail()->status)->toBe(ScheduleStatus::Draft);
});

it('publie une semaine dont les rendez-vous sont couverts par des quarts en brouillon', function () {
    appointmentFor($this->employee, '2026-10-06');
    Schedule::factory()->forDate('2026-10-06')->create(['user_id' => $this->employee->id, 'start_time' => '09:00', 'end_time' => '17:00']);

    nextWeekEditor()->call('publishWeek');

    expect(Schedule::firstOrFail()->status)->toBe(ScheduleStatus::Published);
});

// ── Réassignation depuis l'horaire ─────────────────────────────────────────

it('réassigne un rendez-vous à un autre employé qui travaille ce jour-là', function () {
    $appointment = appointmentFor($this->employee, '2026-10-06');
    $colleague = User::factory()->create(['is_active' => true]);
    Schedule::factory()->forDate('2026-10-06')->published()->create(['user_id' => $colleague->id, 'start_time' => '09:00', 'end_time' => '17:00']);

    nextWeekEditor()
        ->call('openReassign', $appointment->id)
        ->assertSet('showReassignModal', true)
        ->assertSee($colleague->fullName().' — 09:00–17:00')
        ->set('reassignUserId', $colleague->id)
        ->set('reassignStartMinute', 14 * 60)
        ->call('reassign')
        ->assertHasNoErrors()
        ->assertSet('showReassignModal', false);

    expect($appointment->fresh())
        ->user_id->toBe($colleague->id)
        ->date->toBe('2026-10-06')
        ->start_minute->toBe(14 * 60)
        ->duration_minutes->toBe(60);
});

it('refuse de réassigner à un employé absent ce jour-là', function () {
    $appointment = appointmentFor($this->employee, '2026-10-06');
    $colleague = User::factory()->create(['is_active' => true]);
    Schedule::factory()->forDate('2026-10-06')->absence(ScheduleType::Vacation)->create(['user_id' => $colleague->id]);

    nextWeekEditor()
        ->call('openReassign', $appointment->id)
        ->set('reassignUserId', $colleague->id)
        ->call('reassign')
        ->assertHasErrors('reassignUserId');

    expect($appointment->fresh()->user_id)->toBe($this->employee->id);
});

it('refuse de réassigner hors du quart publié de l\'employé visé', function () {
    $appointment = appointmentFor($this->employee, '2026-10-06', 11 * 60);
    $colleague = User::factory()->create(['is_active' => true]);
    Schedule::factory()->forDate('2026-10-06')->published()->create(['user_id' => $colleague->id, 'start_time' => '09:00', 'end_time' => '11:30']);

    nextWeekEditor()
        ->call('openReassign', $appointment->id)
        ->set('reassignUserId', $colleague->id)
        ->call('reassign')
        ->assertHasErrors('reassignUserId');

    expect($appointment->fresh()->user_id)->toBe($this->employee->id);
});

it('refuse de réassigner par-dessus un rendez-vous de l\'employé visé', function () {
    $appointment = appointmentFor($this->employee, '2026-10-06');
    $colleague = User::factory()->create(['is_active' => true]);
    appointmentFor($colleague, '2026-10-06', 10 * 60 + 30, 30);

    nextWeekEditor()
        ->call('openReassign', $appointment->id)
        ->set('reassignUserId', $colleague->id)
        ->call('reassign')
        ->assertHasErrors('reassignUserId');

    expect($appointment->fresh()->user_id)->toBe($this->employee->id);
});

it('refuse de réassigner un rendez-vous modifié entre-temps', function () {
    $appointment = appointmentFor($this->employee, '2026-10-06');
    $colleague = User::factory()->create(['is_active' => true]);

    $component = nextWeekEditor()->call('openReassign', $appointment->id);

    Carbon::setTestNow(Carbon::now()->addMinute());
    $appointment->update(['title' => 'Modifié ailleurs']);

    $component
        ->set('reassignUserId', $colleague->id)
        ->call('reassign')
        ->assertHasErrors('reassignUserId');

    expect($appointment->fresh()->user_id)->toBe($this->employee->id);
});
