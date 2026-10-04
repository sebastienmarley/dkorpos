<?php

use App\Enums\ScheduleStatus;
use App\Enums\ScheduleType;
use App\Livewire\Schedules\Appointments;
use App\Livewire\Schedules\Holidays;
use App\Livewire\Schedules\ScheduleEdit;
use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WeekTemplate;
use App\Models\WeekTemplateEntry;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-28 07:00:00'));

    $this->admin = User::factory()->withRole('admin')->create();
    $this->employee = User::factory()->create();
    $this->actingAs($this->admin);

    $this->date = '2026-10-13';
});

afterEach(fn () => Carbon::setTestNow());

function absenceEditor(User $employee, string $date)
{
    return Livewire::test(ScheduleEdit::class)->call('openCell', $employee->id, $date);
}

function bookingFor(User $employee, string $date)
{
    return Livewire::test(Appointments::class)
        ->set('selectedUserId', $employee->id)
        ->call('openCell', $date, 9 * 60);
}

// ── Absences ───────────────────────────────────────────────────────────────

it('enregistre une absence sans heures', function (ScheduleType $type) {
    absenceEditor($this->employee, $this->date)
        ->set('scheduleType', $type->value)
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->set('breakMinutes', 30)
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::firstOrFail())
        ->type->toBe($type)
        ->start_time->toBeNull()
        ->end_time->toBeNull()
        ->break_minutes->toBe(0);
})->with([ScheduleType::Sick, ScheduleType::Absent, ScheduleType::Vacation, ScheduleType::Holiday]);

it('enregistre une absence sur plusieurs jours', function () {
    absenceEditor($this->employee, $this->date)
        ->set('scheduleType', 'vacation')
        ->set('untilDate', '2026-10-16')
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::orderBy('date')->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all())
        ->toBe(['2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16'])
        ->and(Schedule::where('type', 'vacation')->count())->toBe(4);
});

it('remplace les quarts modifiables mais épargne les quarts payés dans une absence multiple', function () {
    Schedule::factory()->forDate('2026-10-14')->published()->create(['user_id' => $this->employee->id]);
    Schedule::factory()->forDate('2026-10-15')->withStatus(ScheduleStatus::Paid)->create(['user_id' => $this->employee->id]);

    absenceEditor($this->employee, $this->date)
        ->set('scheduleType', 'sick')
        ->set('untilDate', '2026-10-16')
        ->call('save');

    $byDate = Schedule::all()->keyBy(fn ($s) => Carbon::parse($s->date)->toDateString());

    expect($byDate['2026-10-14']->type)->toBe(ScheduleType::Sick)
        ->and($byDate['2026-10-15']->type)->toBe(ScheduleType::Work)
        ->and($byDate['2026-10-15']->status)->toBe(ScheduleStatus::Paid)
        ->and($byDate['2026-10-16']->type)->toBe(ScheduleType::Sick);
});

it('ignore les jours d\'une absence multiple qui ont des rendez-vous', function () {
    Appointment::factory()->create(['user_id' => $this->employee->id, 'date' => '2026-10-14', 'start_minute' => 600]);

    absenceEditor($this->employee, $this->date)
        ->set('scheduleType', 'vacation')
        ->set('untilDate', '2026-10-15')
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::query()->whereDate('date', '2026-10-14')->exists())->toBeFalse()
        ->and(Schedule::count())->toBe(2);
});

it('refuse une absence le jour d\'un rendez-vous', function () {
    Appointment::factory()->create(['user_id' => $this->employee->id, 'date' => $this->date, 'start_minute' => 600]);

    absenceEditor($this->employee, $this->date)
        ->set('scheduleType', 'sick')
        ->call('save')
        ->assertHasErrors('editingDate');

    expect(Schedule::count())->toBe(0);
});

it('refuse une date de fin antérieure au début de l\'absence', function () {
    absenceEditor($this->employee, $this->date)
        ->set('scheduleType', 'sick')
        ->set('untilDate', '2026-10-01')
        ->call('save')
        ->assertHasErrors('untilDate');
});

it('refuse un type inconnu', function () {
    absenceEditor($this->employee, $this->date)
        ->set('scheduleType', 'sabbatique')
        ->call('save')
        ->assertHasErrors('scheduleType');
});

// ── Prise de rendez-vous ───────────────────────────────────────────────────

it('bloque les rendez-vous un jour d\'absence, même en brouillon', function () {
    Schedule::factory()->forDate($this->date)->absence(ScheduleType::Vacation)->create(['user_id' => $this->employee->id]);

    bookingFor($this->employee, $this->date)->assertSet('showModal', false);
});

it('ne bloque pas les autres employés ni les autres jours', function () {
    $other = User::factory()->create();
    Schedule::factory()->forDate($this->date)->absence(ScheduleType::Sick)->create(['user_id' => $this->employee->id]);

    bookingFor($other, $this->date)->assertSet('showModal', true);
    bookingFor($this->employee, '2026-10-14')->assertSet('showModal', true);
});

it('bloque les rendez-vous un férié fermé', function () {
    Holiday::factory()->create(['date' => $this->date]);

    bookingFor($this->employee, $this->date)->assertSet('showModal', false);
});

it('permet les rendez-vous un férié fermé si l\'employé a un quart publié', function () {
    Holiday::factory()->create(['date' => $this->date]);
    Schedule::factory()->forDate($this->date)->published()->create(['user_id' => $this->employee->id]);

    bookingFor($this->employee, $this->date)->assertSet('showModal', true);
});

it('ne bloque pas les rendez-vous un férié ouvert', function () {
    Holiday::factory()->open()->create(['date' => $this->date]);

    bookingFor($this->employee, $this->date)->assertSet('showModal', true);
});

it('affiche le motif du blocage sous le jour', function () {
    Holiday::factory()->create(['date' => $this->date, 'name' => 'Jour de test']);
    Schedule::factory()->forDate('2026-10-14')->absence(ScheduleType::Vacation)->create(['user_id' => $this->employee->id]);

    Livewire::test(Appointments::class)
        ->set('selectedUserId', $this->employee->id)
        ->set('weekStart', '2026-10-11')
        ->assertSee('Jour de test')
        ->assertSee('Vacances');
});

// ── Copie et semaines type ─────────────────────────────────────────────────

it('ne copie pas les absences de la semaine précédente', function () {
    Schedule::factory()->forDate('2026-09-29')->absence(ScheduleType::Sick)->create(['user_id' => $this->employee->id]);

    Livewire::test(ScheduleEdit::class)->set('weekStart', '2026-10-04')->call('copyPreviousWeek');

    expect(Schedule::count())->toBe(1);
});

it('n\'applique pas un quart de semaine type un férié fermé', function () {
    $week = WeekTemplate::factory()->create();
    $shift = ShiftTemplate::factory()->create();
    WeekTemplateEntry::factory()->create(['week_template_id' => $week->id, 'user_id' => $this->employee->id, 'shift_template_id' => $shift->id, 'weekday' => 2]);

    Holiday::factory()->create(['date' => $this->date]);

    Livewire::test(ScheduleEdit::class)->set('weekStart', '2026-10-11')->call('applyWeekTemplate', $week->id);

    expect(Schedule::count())->toBe(0);
});

// ── Gestion des fériés ─────────────────────────────────────────────────────

it('redirige les invités vers la connexion', function () {
    auth()->logout();

    $this->get(route('schedules.holidays'))->assertRedirect(route('login'));
});

it('crée, modifie et supprime un férié', function () {
    $component = Livewire::test(Holidays::class)
        ->call('openCreate')
        ->set('name', 'Jour spécial')
        ->set('date', $this->date)
        ->set('isClosed', false)
        ->call('save')
        ->assertHasNoErrors();

    $holiday = Holiday::firstOrFail();
    expect($holiday)->name->toBe('Jour spécial')->is_closed->toBeFalse();

    $component->call('openEdit', $holiday->id)->set('name', 'Autre nom')->call('save')->assertHasNoErrors();
    expect($holiday->fresh()->name)->toBe('Autre nom');

    $component->call('delete', $holiday->id);
    expect(Holiday::count())->toBe(0);
});

it('refuse deux fériés à la même date', function () {
    Holiday::factory()->create(['date' => $this->date]);

    Livewire::test(Holidays::class)
        ->call('openCreate')
        ->set('name', 'Doublon')
        ->set('date', $this->date)
        ->call('save')
        ->assertHasErrors('date');
});

it('refuse de fermer le magasin un jour où des rendez-vous sont déjà pris', function () {
    Appointment::factory()->create(['user_id' => $this->employee->id, 'date' => $this->date, 'start_minute' => 600]);

    Livewire::test(Holidays::class)
        ->call('openCreate')
        ->set('name', 'Trop tard')
        ->set('date', $this->date)
        ->set('isClosed', true)
        ->call('save')
        ->assertHasErrors('isClosed');

    expect(Holiday::count())->toBe(0);
});

it('accepte un férié ouvert malgré les rendez-vous', function () {
    Appointment::factory()->create(['user_id' => $this->employee->id, 'date' => $this->date, 'start_minute' => 600]);

    Livewire::test(Holidays::class)
        ->call('openCreate')
        ->set('name', 'Ouvert')
        ->set('date', $this->date)
        ->set('isClosed', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Holiday::count())->toBe(1);
});

it('génère les fériés du Québec aux bonnes dates', function (int $year, array $expected) {
    Livewire::test(Holidays::class)->set('year', $year)->call('generateQuebec');

    expect(Holiday::orderBy('date')->pluck('date')->all())->toBe($expected);
})->with([
    2026 => [2026, ['2026-01-01', '2026-04-03', '2026-05-18', '2026-06-24', '2026-07-01', '2026-09-07', '2026-10-12', '2026-12-25']],
    2027 => [2027, ['2027-01-01', '2027-03-26', '2027-05-24', '2027-06-24', '2027-07-01', '2027-09-06', '2027-10-11', '2027-12-25']],
]);

it('ne duplique ni n\'écrase les fériés existants en générant', function () {
    Holiday::factory()->open()->create(['date' => '2026-12-25', 'name' => 'Noël personnalisé']);

    $component = Livewire::test(Holidays::class)->set('year', 2026)->call('generateQuebec')->call('generateQuebec');

    expect(Holiday::count())->toBe(8)
        ->and(Holiday::where('date', '2026-12-25')->firstOrFail())->name->toBe('Noël personnalisé')->is_closed->toBeFalse();
});
