<?php

use App\Enums\ScheduleStatus;
use App\Livewire\Schedules\Index;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// ── Accès ──────────────────────────────────────────────────────────────────

it('redirige les invités vers la page de connexion', function () {
    $this->get(route('schedules.index'))->assertRedirect(route('login'));
});

it('autorise les utilisateurs authentifiés à accéder à la page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('schedules.index'))->assertOk();
});

// ── Affichage ──────────────────────────────────────────────────────────────

it('affiche uniquement les horaires de l\'utilisateur connecté', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $monday = Carbon::now()->startOfWeek()->toDateString();

    Schedule::factory()->forDate($monday)->published()->create(['user_id' => $user->id, 'start_time' => '08:00', 'end_time' => '16:00']);
    Schedule::factory()->forDate($monday)->published()->create(['user_id' => $other->id, 'start_time' => '10:00', 'end_time' => '18:00']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('08:00')
        ->assertDontSee('10:00');
});

it('affiche le badge Aujourd\'hui pour la date courante', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee("Aujourd'hui");
});

it('calcule la durée en déduisant la pause', function () {
    $user = User::factory()->create();

    $monday = Carbon::now()->startOfWeek()->toDateString();

    Schedule::factory()->forDate($monday)->published()->create([
        'user_id' => $user->id,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'break_minutes' => 30,
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('7h30');
});

it('affiche une durée correcte sans pause', function () {
    $user = User::factory()->create();

    $monday = Carbon::now()->startOfWeek()->toDateString();

    Schedule::factory()->forDate($monday)->published()->create([
        'user_id' => $user->id,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'break_minutes' => 0,
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('8h00');
});

it('n\'affiche pas les quarts non publiés', function () {
    $user = User::factory()->create();

    $monday = Carbon::now()->startOfWeek()->toDateString();

    Schedule::factory()->forDate($monday)->create([
        'user_id' => $user->id,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'status' => ScheduleStatus::Draft,
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertDontSee('08:00');
});

// ── Navigation ─────────────────────────────────────────────────────────────

it('navigue à la semaine précédente', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(Index::class);

    $initialWeek = $component->get('weekStart');

    $component->call('previousWeek');

    expect($component->get('weekStart'))
        ->toBe(Carbon::parse($initialWeek)->subWeek()->toDateString());
});

it('navigue à la semaine suivante', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(Index::class);

    $initialWeek = $component->get('weekStart');

    $component->call('nextWeek');

    expect($component->get('weekStart'))
        ->toBe(Carbon::parse($initialWeek)->addWeek()->toDateString());
});

it('retourne à la semaine courante', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('previousWeek')
        ->call('previousWeek')
        ->call('goToCurrentWeek')
        ->assertSet('weekStart', Carbon::now()->startOfWeek()->toDateString());
});

it('affiche les horaires de la semaine naviguée', function () {
    $user = User::factory()->create();

    $lastWeekMonday = Carbon::now()->subWeek()->startOfWeek()->toDateString();

    Schedule::factory()->forDate($lastWeekMonday)->published()->create([
        'user_id' => $user->id,
        'start_time' => '07:00',
        'end_time' => '15:00',
        'break_minutes' => 0,
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('previousWeek')
        ->assertSee('07:00');
});
