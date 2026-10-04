<?php

use App\Enums\ScheduleStatus;
use App\Livewire\Schedules\ScheduleEdit;
use App\Livewire\Schedules\Templates;
use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WeekTemplate;
use App\Models\WeekTemplateEntry;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->withRole('admin')->create();
    $this->actingAs($this->admin);
});

// ── Accès ──────────────────────────────────────────────────────────────────

it('redirige les invités vers la connexion', function () {
    auth()->logout();

    $this->get(route('schedules.templates'))->assertRedirect(route('login'));
});

it('affiche la page des modèles', function () {
    $this->get(route('schedules.templates'))->assertOk();
});

// ── Quarts type ────────────────────────────────────────────────────────────

it('crée un quart type', function () {
    Livewire::test(Templates::class)
        ->call('openCreateShift')
        ->set('shiftName', 'Ouverture')
        ->set('shiftStart', '08:00')
        ->set('shiftEnd', '16:00')
        ->set('shiftBreak', 30)
        ->call('saveShift')
        ->assertHasNoErrors()
        ->assertSet('showShiftModal', false);

    $this->assertDatabaseHas('shift_templates', ['name' => 'Ouverture', 'break_minutes' => 30]);
});

it('valide le quart type', function (array $input, string $field) {
    ShiftTemplate::factory()->create(['name' => 'Existant']);

    Livewire::test(Templates::class)
        ->call('openCreateShift')
        ->set('shiftName', $input['name'] ?? 'Nouveau')
        ->set('shiftStart', $input['start'] ?? '08:00')
        ->set('shiftEnd', $input['end'] ?? '16:00')
        ->set('shiftBreak', $input['break'] ?? 0)
        ->call('saveShift')
        ->assertHasErrors($field);
})->with([
    'nom requis' => [['name' => ''], 'shiftName'],
    'nom déjà pris' => [['name' => 'Existant'], 'shiftName'],
    'fin avant début' => [['start' => '16:00', 'end' => '08:00'], 'shiftEnd'],
    'pause invalide' => [['break' => 45], 'shiftBreak'],
]);

it('modifie un quart type sans conflit avec son propre nom', function () {
    $shift = ShiftTemplate::factory()->create(['name' => 'Ouverture']);

    Livewire::test(Templates::class)
        ->call('openEditShift', $shift->id)
        ->assertSet('shiftName', 'Ouverture')
        ->set('shiftEnd', '17:00')
        ->call('saveShift')
        ->assertHasNoErrors();

    expect($shift->fresh()->end_time)->toBe('17:00');
});

it('supprime un quart type et le retire des semaines type', function () {
    $entry = WeekTemplateEntry::factory()->create();

    Livewire::test(Templates::class)->call('deleteShift', $entry->shift_template_id);

    expect(ShiftTemplate::count())->toBe(0)->and(WeekTemplateEntry::count())->toBe(0);
});

// ── Semaines type ──────────────────────────────────────────────────────────

it('crée une semaine type et la sélectionne', function () {
    Livewire::test(Templates::class)
        ->call('openCreateWeek')
        ->set('weekName', 'Semaine normale')
        ->call('saveWeek')
        ->assertHasNoErrors();

    $week = WeekTemplate::firstOrFail();

    expect($week->name)->toBe('Semaine normale');
});

it('refuse deux semaines type du même nom', function () {
    WeekTemplate::factory()->create(['name' => 'Normale']);

    Livewire::test(Templates::class)
        ->call('openCreateWeek')
        ->set('weekName', 'Normale')
        ->call('saveWeek')
        ->assertHasErrors('weekName');
});

it('enregistre et retire le quart type d\'une case de semaine type', function () {
    $week = WeekTemplate::factory()->create();
    $shift = ShiftTemplate::factory()->create();
    $employee = User::factory()->create();

    $component = Livewire::test(Templates::class)
        ->set('selectedWeekTemplateId', (string) $week->id)
        ->set('entries.'.$employee->id.'.1', (string) $shift->id);

    $this->assertDatabaseHas('week_template_entries', [
        'week_template_id' => $week->id,
        'user_id' => $employee->id,
        'weekday' => 1,
        'shift_template_id' => $shift->id,
    ]);

    $component->set('entries.'.$employee->id.'.1', '');

    expect(WeekTemplateEntry::count())->toBe(0);
});

it('remplace le quart type d\'une case sans doublon', function () {
    $week = WeekTemplate::factory()->create();
    [$first, $second] = ShiftTemplate::factory()->count(2)->create();
    $employee = User::factory()->create();

    Livewire::test(Templates::class)
        ->set('selectedWeekTemplateId', (string) $week->id)
        ->set('entries.'.$employee->id.'.2', (string) $first->id)
        ->set('entries.'.$employee->id.'.2', (string) $second->id);

    expect(WeekTemplateEntry::count())->toBe(1)
        ->and(WeekTemplateEntry::firstOrFail()->shift_template_id)->toBe($second->id);
});

it('ignore une case pour un employé inactif', function () {
    $week = WeekTemplate::factory()->create();
    $shift = ShiftTemplate::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);

    Livewire::test(Templates::class)
        ->set('selectedWeekTemplateId', (string) $week->id)
        ->set('entries.'.$inactive->id.'.1', (string) $shift->id);

    expect(WeekTemplateEntry::count())->toBe(0);
});

it('supprime une semaine type avec ses cases', function () {
    $entry = WeekTemplateEntry::factory()->create();

    Livewire::test(Templates::class)
        ->set('selectedWeekTemplateId', (string) $entry->week_template_id)
        ->call('deleteWeek');

    expect(WeekTemplate::count())->toBe(0)->and(WeekTemplateEntry::count())->toBe(0);
});

it('enregistre une case quand Livewire envoie le tableau de l\'employé', function () {
    $week = WeekTemplate::factory()->create();
    $shift = ShiftTemplate::factory()->create();
    $employee = User::factory()->create();

    Livewire::test(Templates::class)
        ->set('selectedWeekTemplateId', (string) $week->id)
        ->set('entries.'.$employee->id, [3 => (string) $shift->id]);

    $this->assertDatabaseHas('week_template_entries', [
        'week_template_id' => $week->id,
        'user_id' => $employee->id,
        'weekday' => 3,
        'shift_template_id' => $shift->id,
    ]);
});

it('enregistre les cases quand Livewire envoie tout le tableau', function () {
    $week = WeekTemplate::factory()->create();
    $shift = ShiftTemplate::factory()->create();
    $employee = User::factory()->create();

    Livewire::test(Templates::class)
        ->set('selectedWeekTemplateId', (string) $week->id)
        ->set('entries', [$employee->id => [1 => (string) $shift->id, 4 => (string) $shift->id]]);

    expect(WeekTemplateEntry::where('user_id', $employee->id)->pluck('weekday')->sort()->values()->all())->toBe([1, 4]);
});

// ── Quart type dans la fenêtre d'un quart ──────────────────────────────────

it('remplit les heures et la pause depuis un quart type', function () {
    $shift = ShiftTemplate::factory()->create(['start_time' => '07:30', 'end_time' => '15:30', 'break_minutes' => 60]);
    $employee = User::factory()->create();

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $employee->id, Carbon::today()->addDay()->toDateString())
        ->set('shiftTemplateId', (string) $shift->id)
        ->assertSet('startTime', '07:30')
        ->assertSet('endTime', '15:30')
        ->assertSet('breakMinutes', 60);
});

// ── Copie de la semaine précédente ─────────────────────────────────────────

function nextWeekStart(): string
{
    return Carbon::now()->startOfWeek(Carbon::SUNDAY)->addWeek()->toDateString();
}

it('copie les quarts de la semaine précédente en brouillon', function () {
    $employee = User::factory()->create();
    $source = Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(2);

    Schedule::factory()->forDate($source->toDateString())->published()->withNotes('Inventaire')->create([
        'user_id' => $employee->id,
        'start_time' => '10:00',
        'end_time' => '18:00',
        'break_minutes' => 60,
    ]);

    Livewire::test(ScheduleEdit::class)
        ->set('weekStart', nextWeekStart())
        ->call('copyPreviousWeek')
        ->assertDispatched('toast-show');

    $copy = Schedule::query()->whereDate('date', $source->copy()->addWeek()->toDateString())->firstOrFail();

    expect($copy)
        ->user_id->toBe($employee->id)
        ->start_time->toBe('10:00')
        ->end_time->toBe('18:00')
        ->break_minutes->toBe(60)
        ->notes->toBe('Inventaire')
        ->status->toBe(ScheduleStatus::Draft)
        ->and(Schedule::count())->toBe(2);
});

it('ne remplace pas un quart déjà présent en copiant', function () {
    $employee = User::factory()->create();
    $source = Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(2);

    Schedule::factory()->forDate($source->toDateString())->create(['user_id' => $employee->id, 'end_time' => '17:00']);
    Schedule::factory()->forDate($source->copy()->addWeek()->toDateString())->withStatus(ScheduleStatus::Paid)->create([
        'user_id' => $employee->id,
        'end_time' => '12:00',
    ]);

    Livewire::test(ScheduleEdit::class)->set('weekStart', nextWeekStart())->call('copyPreviousWeek');

    $existing = Schedule::query()->whereDate('date', $source->copy()->addWeek()->toDateString())->firstOrFail();

    expect(Schedule::count())->toBe(2)
        ->and($existing->end_time)->toBe('12:00')
        ->and($existing->status)->toBe(ScheduleStatus::Paid);
});

it('ne copie pas vers un employé inactif ou hors de sa période de travail', function () {
    $source = Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(2);
    $inactive = User::factory()->create(['is_active' => false]);
    $left = User::factory()->create(['last_day' => $source->toDateString()]);

    foreach ([$inactive, $left] as $employee) {
        Schedule::factory()->forDate($source->toDateString())->create(['user_id' => $employee->id]);
    }

    Livewire::test(ScheduleEdit::class)->set('weekStart', nextWeekStart())->call('copyPreviousWeek');

    expect(Schedule::count())->toBe(2);
});

it('ne copie pas un quart qui laisserait un rendez-vous hors horaire', function () {
    $employee = User::factory()->create();
    $source = Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(2);
    $target = $source->copy()->addWeek()->toDateString();

    Schedule::factory()->forDate($source->toDateString())->create(['user_id' => $employee->id, 'start_time' => '09:00', 'end_time' => '12:00']);
    Appointment::factory()->create(['user_id' => $employee->id, 'date' => $target, 'start_minute' => 15 * 60, 'duration_minutes' => 60]);

    Livewire::test(ScheduleEdit::class)->set('weekStart', nextWeekStart())->call('copyPreviousWeek');

    expect(Schedule::query()->whereDate('date', $target)->exists())->toBeFalse();
});

// ── Application d'une semaine type ─────────────────────────────────────────

it('applique une semaine type sur les bons jours en brouillon', function () {
    $employee = User::factory()->create();
    $week = WeekTemplate::factory()->create();
    $shift = ShiftTemplate::factory()->create(['start_time' => '08:00', 'end_time' => '16:00', 'break_minutes' => 30]);

    WeekTemplateEntry::factory()->create(['week_template_id' => $week->id, 'user_id' => $employee->id, 'shift_template_id' => $shift->id, 'weekday' => 1]);
    WeekTemplateEntry::factory()->create(['week_template_id' => $week->id, 'user_id' => $employee->id, 'shift_template_id' => $shift->id, 'weekday' => 6]);

    Livewire::test(ScheduleEdit::class)
        ->set('weekStart', nextWeekStart())
        ->call('applyWeekTemplate', $week->id);

    $start = Carbon::parse(nextWeekStart());

    expect(Schedule::orderBy('date')->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all())
        ->toBe([$start->copy()->addDay()->toDateString(), $start->copy()->addDays(6)->toDateString()])
        ->and(Schedule::firstOrFail())
        ->start_time->toBe('08:00')
        ->end_time->toBe('16:00')
        ->break_minutes->toBe(30)
        ->status->toBe(ScheduleStatus::Draft);
});

it('n\'écrase pas un quart existant en appliquant une semaine type', function () {
    $employee = User::factory()->create();
    $week = WeekTemplate::factory()->create();
    $shift = ShiftTemplate::factory()->create();
    $date = Carbon::parse(nextWeekStart())->addDay()->toDateString();

    Schedule::factory()->forDate($date)->create(['user_id' => $employee->id, 'end_time' => '12:00']);
    WeekTemplateEntry::factory()->create(['week_template_id' => $week->id, 'user_id' => $employee->id, 'shift_template_id' => $shift->id, 'weekday' => 1]);

    Livewire::test(ScheduleEdit::class)->set('weekStart', nextWeekStart())->call('applyWeekTemplate', $week->id);

    expect(Schedule::count())->toBe(1)->and(Schedule::firstOrFail()->end_time)->toBe('12:00');
});
