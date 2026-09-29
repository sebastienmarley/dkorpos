<?php

use App\Enums\ScheduleStatus;
use App\Livewire\Schedules\ScheduleEdit;
use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// ── Accès ──────────────────────────────────────────────────────────────────

it('redirige les invités vers la page de connexion', function () {
    $this->get(route('schedules.schedule-edit'))->assertRedirect(route('login'));
});

it('autorise les utilisateurs authentifiés à accéder à la page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('schedules.schedule-edit'))->assertOk();
});

// ── Affichage ──────────────────────────────────────────────────────────────

it('affiche les employés actifs dans le tableau', function () {
    $active = User::factory()->create(['firstname' => 'Alice', 'lastname' => 'Roy', 'is_active' => true]);
    User::factory()->create(['is_active' => false]);

    $this->actingAs($active);

    Livewire::test(ScheduleEdit::class)
        ->assertSee('Alice Roy');
});

it('n\'affiche pas les employés inactifs', function () {
    $user = User::factory()->create();
    $inactive = User::factory()->create(['firstname' => 'Bob', 'lastname' => 'Inactif', 'is_active' => false]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->assertDontSee('Bob Inactif');
});

it('affiche les heures déjà saisies dans les cellules', function () {
    $user = User::factory()->create();

    $monday = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Schedule::factory()->forDate($monday)->create([
        'user_id' => $user->id,
        'start_time' => '08:30',
        'end_time' => '16:30',
    ]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->assertSee('08:30')
        ->assertSee('16:30');
});

it('calcule le total des heures hebdomadaires en déduisant les pauses', function () {
    $user = User::factory()->create();

    $start = Carbon::now()->startOfWeek(Carbon::SUNDAY);

    Schedule::factory()->forDate($start->toDateString())->create([
        'user_id' => $user->id,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'break_minutes' => 30,
    ]);

    Schedule::factory()->forDate($start->copy()->addDay()->toDateString())->create([
        'user_id' => $user->id,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'break_minutes' => 30,
    ]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->assertSee('15h00');
});

// ── Ouverture du modal ─────────────────────────────────────────────────────

it('ouvre le modal avec les champs vides pour une nouvelle cellule', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->assertSet('showModal', true)
        ->assertSet('startTime', '')
        ->assertSet('endTime', '')
        ->assertSet('breakMinutes', 0)
        ->assertSet('editingUserId', $user->id)
        ->assertSet('editingDate', $date);
});

it('ouvre le modal avec les données existantes pour une cellule déjà remplie', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Schedule::factory()->forDate($date)->create([
        'user_id' => $user->id,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'break_minutes' => 60,
        'notes' => 'Réunion matin',
    ]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->assertSet('startTime', '09:00')
        ->assertSet('endTime', '17:00')
        ->assertSet('breakMinutes', 60)
        ->assertSet('notes', 'Réunion matin');
});

// ── Sauvegarde ─────────────────────────────────────────────────────────────

it('crée un nouvel horaire pour une cellule vide', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '08:00')
        ->set('endTime', '16:00')
        ->set('breakMinutes', 30)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $schedule = Schedule::where('user_id', $user->id)->whereDate('date', $date)->first();

    expect($schedule)->not->toBeNull();
    expect($schedule->start_time)->toBe('08:00');
    expect($schedule->end_time)->toBe('16:00');
    expect($schedule->break_minutes)->toBe(30);
});

it('met à jour un horaire existant sans créer de doublon', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Schedule::factory()->forDate($date)->create(['user_id' => $user->id, 'start_time' => '09:00', 'end_time' => '17:00']);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '10:00')
        ->set('endTime', '18:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::where('user_id', $user->id)->whereDate('date', $date)->count())->toBe(1);
    expect(Schedule::where('user_id', $user->id)->whereDate('date', $date)->value('start_time'))->toBe('10:00');
});

it('sauvegarde les notes', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->set('notes', 'Journée spéciale')
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::where('user_id', $user->id)->whereDate('date', $date)->value('notes'))
        ->toBe('Journée spéciale');
});

it('permet de sauvegarder sans heures', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('notes', 'Congé')
        ->call('save')
        ->assertHasNoErrors();
});

it('ferme le modal après une sauvegarde réussie', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertSet('showModal', false);
});

// ── Validation ─────────────────────────────────────────────────────────────

it('rejette un format d\'heure invalide', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', 'abc')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasErrors(['startTime']);
});

it('rejette une heure de fin antérieure à l\'heure de début', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '17:00')
        ->set('endTime', '09:00')
        ->call('save')
        ->assertHasErrors(['endTime']);
});

it('rejette une heure de début sans heure de fin', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '09:00')
        ->call('save')
        ->assertHasErrors(['endTime']);
});

it('rejette une heure de fin sans heure de début', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasErrors(['startTime']);
});

it('rejette une valeur de pause non autorisée', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->set('breakMinutes', 45)
        ->call('save')
        ->assertHasErrors(['breakMinutes']);
});

// ── Statut ─────────────────────────────────────────────────────────────────

it('crée un quart avec le statut non publiée par défaut', function () {
    $user = User::factory()->create();
    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save');

    expect(Schedule::where('user_id', $user->id)->whereDate('date', $date)->first()->status)
        ->toBe(ScheduleStatus::Draft);
});

it('sauvegarde le statut publiée', function () {
    $user = User::factory()->create();
    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->set('status', 'published')
        ->call('save');

    expect(Schedule::where('user_id', $user->id)->whereDate('date', $date)->first()->status)
        ->toBe(ScheduleStatus::Published);
});

it('charge le statut existant à l\'ouverture du modal', function () {
    $user = User::factory()->create();
    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Schedule::factory()->forDate($date)->published()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->assertSet('status', 'published');
});

it('refuse de modifier un quart avec le statut fermée', function () {
    $user = User::factory()->create();
    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Schedule::factory()->forDate($date)->withStatus(ScheduleStatus::Closed)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '10:00')
        ->set('endTime', '18:00')
        ->call('save')
        ->assertHasErrors(['editingDate']);
});

it('refuse de modifier un quart avec le statut payée', function () {
    $user = User::factory()->create();
    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Schedule::factory()->forDate($date)->withStatus(ScheduleStatus::Paid)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '10:00')
        ->set('endTime', '18:00')
        ->call('save')
        ->assertHasErrors(['editingDate']);
});

// ── Publication de semaine ─────────────────────────────────────────────────

it('publie tous les quarts de la semaine', function () {
    $user = User::factory()->create();
    $start = Carbon::now()->startOfWeek(Carbon::SUNDAY);

    Schedule::factory()->forDate($start->toDateString())->create(['user_id' => $user->id]);
    Schedule::factory()->forDate($start->copy()->addDay()->toDateString())->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('publishWeek');

    expect(Schedule::where('user_id', $user->id)->get()->every(fn ($s) => $s->status === ScheduleStatus::Published))
        ->toBeTrue();
});

it('dépublie les quarts draft et published de la semaine', function () {
    $user = User::factory()->create();
    $start = Carbon::now()->startOfWeek(Carbon::SUNDAY);

    Schedule::factory()->forDate($start->toDateString())->published()->create(['user_id' => $user->id]);
    Schedule::factory()->forDate($start->copy()->addDay()->toDateString())->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('unpublishWeek');

    expect(Schedule::where('user_id', $user->id)->get()->every(fn ($s) => $s->status === ScheduleStatus::Draft))
        ->toBeTrue();
});

it('ne dépublie pas les quarts fermés ou payés', function () {
    $user = User::factory()->create();
    $start = Carbon::now()->startOfWeek(Carbon::SUNDAY);

    Schedule::factory()->forDate($start->toDateString())->withStatus(ScheduleStatus::Closed)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('unpublishWeek');

    expect(Schedule::where('user_id', $user->id)->first()->status)
        ->toBe(ScheduleStatus::Closed);
});

// ── Suppression ────────────────────────────────────────────────────────────

it('supprime un quart de travail existant', function () {
    $user = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Schedule::factory()->forDate($date)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->call('deleteSchedule')
        ->assertSet('showModal', false);

    expect(Schedule::where('user_id', $user->id)->whereDate('date', $date)->exists())->toBeFalse();
});

it('ne supprime pas le quart d\'un autre employé', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Schedule::factory()->forDate($date)->create(['user_id' => $other->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->call('deleteSchedule');

    expect(Schedule::where('user_id', $other->id)->exists())->toBeTrue();
});

// ── Contrainte premier jour ────────────────────────────────────────────────

it('refuse un quart de travail avant le premier jour de l\'employé', function () {
    $user = User::factory()->create([
        'first_day' => '2026-09-15',
    ]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, '2026-09-10')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasErrors(['editingDate']);

    expect(Schedule::where('user_id', $user->id)->count())->toBe(0);
});

it('accepte un quart de travail le jour du premier jour de l\'employé', function () {
    $user = User::factory()->create([
        'first_day' => '2026-09-15',
    ]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, '2026-09-15')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::where('user_id', $user->id)->count())->toBe(1);
});

it('accepte un quart de travail après le premier jour de l\'employé', function () {
    $user = User::factory()->create([
        'first_day' => '2026-09-15',
    ]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, '2026-09-20')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasNoErrors();
});

it('refuse un quart de travail quand l\'employé n\'a pas de date d\'entrée en fonction', function () {
    $user = User::factory()->create(['first_day' => null]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, '2026-09-10')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasErrors(['editingDate']);
});

// ── Contrainte dernier jour ────────────────────────────────────────────────

it('refuse un quart de travail après le dernier jour de l\'employé', function () {
    $user = User::factory()->create([
        'first_day' => '2026-09-01',
        'last_day' => '2026-09-20',
    ]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, '2026-09-25')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasErrors(['editingDate']);

    expect(Schedule::where('user_id', $user->id)->count())->toBe(0);
});

it('accepte un quart de travail le jour du dernier jour de l\'employé', function () {
    $user = User::factory()->create([
        'first_day' => '2026-09-01',
        'last_day' => '2026-09-20',
    ]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, '2026-09-20')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::where('user_id', $user->id)->count())->toBe(1);
});

it('accepte un quart de travail avant le dernier jour de l\'employé', function () {
    $user = User::factory()->create([
        'first_day' => '2026-09-01',
        'last_day' => '2026-09-20',
    ]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, '2026-09-15')
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save')
        ->assertHasNoErrors();
});

// ── Navigation ─────────────────────────────────────────────────────────────

it('navigue à la semaine précédente', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(ScheduleEdit::class);
    $initialWeek = $component->get('weekStart');

    $component->call('previousWeek');

    expect($component->get('weekStart'))
        ->toBe(Carbon::parse($initialWeek)->subWeek()->toDateString());
});

it('navigue à la semaine suivante', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(ScheduleEdit::class);
    $initialWeek = $component->get('weekStart');

    $component->call('nextWeek');

    expect($component->get('weekStart'))
        ->toBe(Carbon::parse($initialWeek)->addWeek()->toDateString());
});

it('retourne à la semaine courante', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('previousWeek')
        ->call('goToCurrentWeek')
        ->assertSet('weekStart', Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString());
});

// ── Publication sûre ───────────────────────────────────────────────────────

it('ne republie pas les quarts fermés ou payés en publiant la semaine', function () {
    $user = User::factory()->create();
    $start = Carbon::now()->startOfWeek(Carbon::SUNDAY);

    Schedule::factory()->forDate($start->toDateString())->withStatus(ScheduleStatus::Closed)->create(['user_id' => $user->id]);
    Schedule::factory()->forDate($start->copy()->addDay()->toDateString())->withStatus(ScheduleStatus::Paid)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)->call('publishWeek');

    expect(Schedule::orderBy('date')->pluck('status')->all())
        ->toBe([ScheduleStatus::Closed, ScheduleStatus::Paid]);
});

it('ne publie pas les quarts des employés inactifs', function () {
    $active = User::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);
    $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

    Schedule::factory()->forDate($date)->create(['user_id' => $inactive->id]);

    $this->actingAs($active);

    Livewire::test(ScheduleEdit::class)->call('publishWeek');

    expect(Schedule::firstOrFail()->status)->toBe(ScheduleStatus::Draft);
});

it('refuse de dépublier une semaine qui contient des rendez-vous à venir', function () {
    $user = User::factory()->create();
    $today = Carbon::today()->toDateString();

    Schedule::factory()->forDate($today)->published()->create(['user_id' => $user->id]);
    Appointment::factory()->create(['user_id' => $user->id, 'date' => $today, 'start_minute' => 600, 'duration_minutes' => 60]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('unpublishWeek')
        ->assertDispatched('toast-show');

    expect(Schedule::firstOrFail()->status)->toBe(ScheduleStatus::Published);
});

// ── Rendez-vous liés ───────────────────────────────────────────────────────

function scheduleWithAppointment(User $user, string $date): void
{
    Schedule::factory()->forDate($date)->published()->create(['user_id' => $user->id, 'start_time' => '09:00', 'end_time' => '17:00']);
    Appointment::factory()->create(['user_id' => $user->id, 'date' => $date, 'start_minute' => 15 * 60, 'duration_minutes' => 60]);
}

it('refuse de raccourcir un quart si un rendez-vous se retrouve hors horaire', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->addDays(2)->toDateString();
    scheduleWithAppointment($user, $date);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('endTime', '15:30')
        ->call('save')
        ->assertHasErrors('editingDate');

    expect(Schedule::firstOrFail()->end_time)->toBe('17:00');
});

it('accepte de modifier un quart tant que les rendez-vous restent dans l\'horaire', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->addDays(2)->toDateString();
    scheduleWithAppointment($user, $date);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('endTime', '16:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::firstOrFail()->end_time)->toBe('16:00');
});

it('refuse de repasser un quart en brouillon si des rendez-vous y sont pris', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->addDays(2)->toDateString();
    scheduleWithAppointment($user, $date);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('status', 'draft')
        ->call('save')
        ->assertHasErrors('editingDate');
});

it('refuse de supprimer un quart qui contient des rendez-vous', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->addDays(2)->toDateString();
    scheduleWithAppointment($user, $date);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->call('deleteSchedule')
        ->assertHasErrors('editingDate');

    expect(Schedule::count())->toBe(1);
});

it('ne tient pas compte des rendez-vous passés', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->subDays(2)->toDateString();
    scheduleWithAppointment($user, $date);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->call('deleteSchedule')
        ->assertHasNoErrors();

    expect(Schedule::count())->toBe(0);
});

// ── Statuts protégés ───────────────────────────────────────────────────────

it('refuse de supprimer un quart payé', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->addDay()->toDateString();

    Schedule::factory()->forDate($date)->withStatus(ScheduleStatus::Paid)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->call('deleteSchedule')
        ->assertHasErrors('editingDate');

    expect(Schedule::count())->toBe(1);
});

it('refuse d\'assigner un statut fermé ou payé depuis le module', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->addDay()->toDateString();

    $this->actingAs($user);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->set('status', 'paid')
        ->call('save')
        ->assertHasErrors('status');
});

// ── Auteur et concurrence ──────────────────────────────────────────────────

it('enregistre l\'auteur de la création et de la dernière modification', function () {
    $creator = User::factory()->create();
    $editor = User::factory()->create();
    $date = Carbon::today()->addDay()->toDateString();

    $this->actingAs($creator);
    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $creator->id, $date)
        ->set('startTime', '09:00')
        ->set('endTime', '17:00')
        ->call('save');

    $this->actingAs($editor);
    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $creator->id, $date)
        ->set('endTime', '16:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Schedule::firstOrFail())
        ->created_by->toBe($creator->id)
        ->last_updated_by->toBe($editor->id);
});

it('refuse d\'enregistrer un quart modifié par quelqu\'un d\'autre entre-temps', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->addDay()->toDateString();
    $schedule = Schedule::factory()->forDate($date)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $component = Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('endTime', '18:00');

    $schedule->forceFill(['end_time' => '12:00', 'updated_at' => now()->addMinute()])->saveQuietly();

    $component->call('save')->assertHasErrors('editingDate');

    expect($schedule->fresh()->end_time)->toBe('12:00');
});

it('refuse d\'enregistrer un quart supprimé par quelqu\'un d\'autre entre-temps', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->addDay()->toDateString();
    $schedule = Schedule::factory()->forDate($date)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $component = Livewire::test(ScheduleEdit::class)->call('openCell', $user->id, $date);

    $schedule->delete();

    $component->call('save')->assertHasErrors('editingDate');

    expect(Schedule::count())->toBe(0);
});

it('refuse de créer un quart déjà créé par quelqu\'un d\'autre entre-temps', function () {
    $user = User::factory()->create();
    $date = Carbon::today()->addDay()->toDateString();

    $this->actingAs($user);

    $component = Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, $date)
        ->set('startTime', '08:00')
        ->set('endTime', '12:00');

    Schedule::factory()->forDate($date)->create(['user_id' => $user->id]);

    $component->call('save')->assertHasErrors('editingDate');

    expect(Schedule::count())->toBe(1)->and(Schedule::firstOrFail()->end_time)->toBe('17:00');
});
