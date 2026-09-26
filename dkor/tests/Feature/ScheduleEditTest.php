<?php

use App\Livewire\Schedules\ScheduleEdit;
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
