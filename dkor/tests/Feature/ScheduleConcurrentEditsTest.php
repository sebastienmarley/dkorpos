<?php

/*
|--------------------------------------------------------------------------
| Modifications simultanées de l'horaire (issue #21)
|--------------------------------------------------------------------------
|
| Le directeur a la semaine du 4 octobre 2026 ouverte pendant que la comptabilité la modifie.
|
*/

use App\Actions\LockPayrollPeriod;
use App\Enums\ScheduleStatus;
use App\Livewire\Schedules\ScheduleEdit;
use App\Models\Schedule;
use App\Models\User;
use App\Models\WeekTemplate;
use App\Models\WeekTemplateEntry;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-28 07:00:00'));

    $this->director = User::factory()->withRole('admin')->create();
    $this->accountant = User::factory()->create(['firstname' => 'Marie', 'lastname' => 'Comptable']);
    $this->employee = User::factory()->create(['is_active' => true, 'first_day' => '2026-01-01']);

    $this->actingAs($this->director);
});

afterEach(fn () => Carbon::setTestNow());

function weekEditor()
{
    return Livewire::test(ScheduleEdit::class)->set('weekStart', '2026-10-04');
}

/** La comptabilité modifie le quart une minute plus tard. */
function changedByAccountant(Schedule $schedule, User $accountant, array $attributes = ['end_time' => '12:00']): void
{
    Carbon::setTestNow(Carbon::now()->addMinute());

    $schedule->forceFill([...$attributes, 'last_updated_by' => $accountant->id, 'updated_at' => Carbon::now()])->saveQuietly();
}

// ── Un quart ───────────────────────────────────────────────────────────────

it('refuse de supprimer un quart modifié par quelqu\'un d\'autre depuis l\'ouverture', function () {
    $schedule = Schedule::factory()->forDate('2026-10-06')->create(['user_id' => $this->employee->id]);

    $component = weekEditor()->call('openCell', $this->employee->id, '2026-10-06');

    changedByAccountant($schedule, $this->accountant);

    $component->call('deleteSchedule')
        ->assertHasErrors('editingDate')
        ->assertSee('modifié entre-temps par Marie Comptable à 07:01');

    expect(Schedule::query()->whereKey($schedule->id)->exists())->toBeTrue();
});

it('refuse d\'enregistrer un quart dans une période de paie verrouillée depuis l\'ouverture', function () {
    $component = weekEditor()
        ->call('openCell', $this->employee->id, '2026-10-06')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00');

    app(LockPayrollPeriod::class)->lock(Carbon::parse('2026-10-06'), $this->accountant);

    $component->call('save')->assertHasErrors('editingDate');

    expect(Schedule::count())->toBe(0);
});

// ── Toute la semaine ───────────────────────────────────────────────────────

it('refuse de publier une semaine modifiée par quelqu\'un d\'autre, puis publie après rafraîchissement', function () {
    $schedule = Schedule::factory()->forDate('2026-10-06')->create(['user_id' => $this->employee->id]);

    $component = weekEditor();

    changedByAccountant($schedule, $this->accountant);

    $component->call('publishWeek')->assertDispatched('toast-show');

    expect($schedule->fresh()->status)->toBe(ScheduleStatus::Draft);

    $component->call('publishWeek');

    expect($schedule->fresh()->status)->toBe(ScheduleStatus::Published);
});

it('refuse de publier une semaine où un quart a été ajouté par quelqu\'un d\'autre', function () {
    $component = weekEditor();

    Schedule::factory()->forDate('2026-10-07')->create(['user_id' => $this->employee->id, 'last_updated_by' => $this->accountant->id]);

    $component->call('publishWeek');

    expect(Schedule::firstOrFail()->status)->toBe(ScheduleStatus::Draft);
});

it('refuse de dépublier une semaine modifiée par quelqu\'un d\'autre', function () {
    $schedule = Schedule::factory()->forDate('2026-10-06')->published()->create(['user_id' => $this->employee->id]);

    $component = weekEditor();

    changedByAccountant($schedule, $this->accountant, ['end_time' => '15:00']);

    $component->call('unpublishWeek');

    expect($schedule->fresh()->status)->toBe(ScheduleStatus::Published);
});

it('refuse de copier la semaine précédente vers une semaine modifiée par quelqu\'un d\'autre', function () {
    Schedule::factory()->forDate('2026-09-29')->create(['user_id' => $this->employee->id]);
    $other = User::factory()->create(['is_active' => true, 'first_day' => '2026-01-01']);
    $schedule = Schedule::factory()->forDate('2026-10-07')->create(['user_id' => $other->id]);

    $component = weekEditor();

    $schedule->delete();

    $component->call('copyPreviousWeek');

    expect(Schedule::query()->whereDate('date', '2026-10-06')->exists())->toBeFalse();
});

it('refuse d\'appliquer une semaine type à une semaine modifiée par quelqu\'un d\'autre', function () {
    $week = WeekTemplate::factory()->create();
    WeekTemplateEntry::factory()->create(['week_template_id' => $week->id, 'weekday' => 2]);
    $schedule = Schedule::factory()->forDate('2026-10-08')->create(['user_id' => $this->employee->id]);

    $component = weekEditor();

    changedByAccountant($schedule, $this->accountant);

    $component->call('applyWeekTemplate', $this->employee->id, $week->id)->assertDispatched('toast-show');

    expect(Schedule::query()->whereDate('date', '2026-10-06')->exists())->toBeFalse();

    $component->call('applyWeekTemplate', $this->employee->id, $week->id);

    expect(Schedule::query()->whereDate('date', '2026-10-06')->exists())->toBeTrue();
});

it('publie après les propres modifications de l\'usager sans les prendre pour celles d\'un autre', function () {
    weekEditor()
        ->call('openCell', $this->employee->id, '2026-10-06')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasNoErrors()
        ->call('publishWeek');

    expect(Schedule::firstOrFail()->status)->toBe(ScheduleStatus::Published);
});

// ── Grille à jour ──────────────────────────────────────────────────────────

it('affiche qui a modifié la semaine, puis retire l\'avis au rafraîchissement', function () {
    $schedule = Schedule::factory()->forDate('2026-10-06')->create(['user_id' => $this->employee->id]);

    $component = weekEditor()->call('checkForChanges')->assertDontSee('a été modifié par');

    changedByAccountant($schedule, $this->accountant);

    $component->call('checkForChanges')
        ->assertSee('L\'horaire de cette semaine a été modifié par Marie Comptable à 07:01.')
        ->call('refreshWeek')
        ->assertDontSee('a été modifié par');
});

it('signale un quart supprimé par quelqu\'un d\'autre', function () {
    $schedule = Schedule::factory()->forDate('2026-10-06')->create(['user_id' => $this->employee->id]);

    $component = weekEditor();

    $schedule->delete();

    $component->call('checkForChanges')->assertSee('Un quart de cette semaine a été supprimé par un autre usager.');
});

it('garde la saisie en cours quand la semaine change pendant l\'édition', function () {
    $schedule = Schedule::factory()->forDate('2026-10-07')->create(['user_id' => $this->employee->id]);

    $component = weekEditor()
        ->call('openCell', $this->employee->id, '2026-10-06')
        ->set('startTime', '10:00')
        ->set('notes', 'Remplace Julie');

    changedByAccountant($schedule, $this->accountant);

    $component->call('checkForChanges')
        ->assertSet('showModal', true)
        ->assertSet('startTime', '10:00')
        ->assertSet('notes', 'Remplace Julie');
});
