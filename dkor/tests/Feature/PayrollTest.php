<?php

use App\Actions\BuildPayrollReport;
use App\Actions\CalculateVacationBalance;
use App\Actions\FillWeekSchedules;
use App\Actions\LockPayrollPeriod;
use App\Enums\ScheduleStatus;
use App\Enums\ScheduleType;
use App\Livewire\Accounting\Payroll;
use App\Livewire\Schedules\ScheduleEdit;
use App\Livewire\Users\Show;
use App\Models\PayrollPeriod;
use App\Models\Permission;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    config(['payroll.period_anchor' => '2026-01-04']);
});

function quart(User $user, string $date, ScheduleType $type = ScheduleType::Work, ScheduleStatus $status = ScheduleStatus::Published): Schedule
{
    $factory = Schedule::factory()->for($user)->forDate($date)->withStatus($status);

    return $type->hasHours()
        ? $factory->create(['type' => $type, 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30])
        : $factory->absence($type)->create();
}

function ligne(User $user, string $periodStart): array
{
    return app(BuildPayrollReport::class)->handle(Carbon::parse($periodStart))->firstWhere('user.id', $user->id);
}

// ── Périodes ───────────────────────────────────────────────────────────────

it('enchaîne des périodes de 2 semaines à partir de la date de départ', function () {
    expect(PayrollPeriod::startFor('2026-01-04')->toDateString())->toBe('2026-01-04')
        ->and(PayrollPeriod::startFor('2026-01-17')->toDateString())->toBe('2026-01-04')
        ->and(PayrollPeriod::startFor('2026-01-18')->toDateString())->toBe('2026-01-18')
        ->and(PayrollPeriod::startFor('2026-01-03')->toDateString())->toBe('2025-12-21')
        ->and(PayrollPeriod::endFor('2026-01-18')->toDateString())->toBe('2026-01-31');
});

// ── Accès ──────────────────────────────────────────────────────────────────

it('réserve la page de paie à payroll.view', function () {
    $this->actingAs(User::factory()->withRole('manager')->create())->get(route('accounting.payroll'))->assertForbidden();
    $this->actingAs(User::factory()->withRole('accounting')->create())->get(route('accounting.payroll'))->assertOk();
});

it('interdit le verrouillage sans payroll.lock', function () {
    $user = User::factory()->withRole('accounting')->create();
    $user->deniedPermissions()->attach(Permission::findByName('payroll.lock'));

    $this->actingAs($user->fresh());

    Livewire::test(Payroll::class)->call('lock')->assertForbidden();
});

// ── Verrouillage ───────────────────────────────────────────────────────────

it('verrouille les horaires de la période sélectionnée seulement', function () {
    $user = User::factory()->create();
    $brouillon = quart($user, '2026-01-05', status: ScheduleStatus::Draft);
    $publie = quart($user, '2026-01-17');
    $paye = quart($user, '2026-01-10', status: ScheduleStatus::Paid);
    $apres = quart($user, '2026-01-18');

    $this->actingAs($admin = User::factory()->withRole('admin')->create());

    Livewire::test(Payroll::class)
        ->set('periodStart', '2026-01-10')
        ->assertSet('periodStart', '2026-01-04')
        ->call('lock')
        ->assertDispatched('toast-show');

    expect($brouillon->fresh()->status)->toBe(ScheduleStatus::Closed)
        ->and($publie->fresh()->status)->toBe(ScheduleStatus::Closed)
        ->and($paye->fresh()->status)->toBe(ScheduleStatus::Paid)
        ->and($apres->fresh()->status)->toBe(ScheduleStatus::Published);

    $period = PayrollPeriod::first();
    expect($period->start_date->toDateString())->toBe('2026-01-04')
        ->and($period->end_date->toDateString())->toBe('2026-01-17')
        ->and($period->locked_by)->toBe($admin->id);
});

it('déverrouille une période et republie ses horaires', function () {
    $user = User::factory()->create();
    $quart = quart($user, '2026-01-05');
    $admin = User::factory()->withRole('admin')->create();

    app(LockPayrollPeriod::class)->lock(Carbon::parse('2026-01-04'), $admin);

    $this->actingAs($admin);

    Livewire::test(Payroll::class)->set('periodStart', '2026-01-04')->call('unlock');

    expect(PayrollPeriod::count())->toBe(0)
        ->and($quart->fresh()->status)->toBe(ScheduleStatus::Published);
});

it('empêche de modifier ou d\'ajouter un horaire dans une période verrouillée', function () {
    $this->travelTo(Carbon::parse('2026-01-20'));

    $user = User::factory()->create(['first_day' => '2025-01-01']);
    $admin = User::factory()->withRole('admin')->create();
    app(LockPayrollPeriod::class)->lock(Carbon::parse('2026-01-04'), $admin);

    $this->actingAs($admin);

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, '2026-01-06')
        ->assertSet('showModal', false)
        ->assertDispatched('toast-show')
        ->set('editingUserId', $user->id)
        ->set('editingDate', '2026-01-06')
        ->set('startTime', '08:00')
        ->set('endTime', '16:00')
        ->call('save')
        ->assertHasErrors(['editingDate']);

    expect(Schedule::where('user_id', $user->id)->count())->toBe(0);

    $result = app(FillWeekSchedules::class)->handle([
        ['user_id' => $user->id, 'date' => '2026-01-06', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30],
    ]);

    expect($result['created'])->toBe(0)->and($result['unavailable'])->toBe(1);
});

// ── Rapport ────────────────────────────────────────────────────────────────

it('calcule les heures travaillées, de formation et de vacances', function () {
    $user = User::factory()->create(['hours_per_day' => '7.50']);
    quart($user, '2026-01-05');
    quart($user, '2026-01-06');
    quart($user, '2026-01-07', ScheduleType::Training);
    quart($user, '2026-01-08', ScheduleType::Vacation);
    quart($user, '2026-01-09', ScheduleType::Vacation);
    quart($user, '2026-01-20');

    $row = ligne($user, '2026-01-04');

    expect($row['worked_hours'])->toBe(15.0)
        ->and($row['training_hours'])->toBe(7.5)
        ->and($row['vacation_hours'])->toBe(15.0)
        ->and($row['bonus'])->toBe(0.0)
        ->and($row['commission'])->toBe(0.0);
});

it('paie la maladie selon le maximum du magasin pour l\'année de maladie', function () {
    $store = Store::factory()->create(['sick_accrual_start' => '01-01', 'sick_days_full_time' => 2, 'sick_days_part_time' => 1]);

    $tempsPlein = User::factory()->create(['store_id' => $store->id, 'is_full_time' => true]);
    quart($tempsPlein, '2025-12-15', ScheduleType::Sick);
    quart($tempsPlein, '2026-01-05', ScheduleType::Sick);
    quart($tempsPlein, '2026-01-06', ScheduleType::Sick);
    quart($tempsPlein, '2026-01-07', ScheduleType::Sick);

    $tempsPartiel = User::factory()->create(['store_id' => $store->id, 'is_full_time' => false]);
    quart($tempsPartiel, '2026-01-05', ScheduleType::Sick);
    quart($tempsPartiel, '2026-01-06', ScheduleType::Sick);

    $sansMagasin = User::factory()->create();
    quart($sansMagasin, '2026-01-05', ScheduleType::Sick);

    // Le 15 décembre appartient à l'année de maladie précédente : 2 jours payés sur 3 en janvier.
    expect(ligne($tempsPlein, '2026-01-04')['sick_paid_hours'])->toBe(16.0)
        ->and(ligne($tempsPartiel, '2026-01-04')['sick_paid_hours'])->toBe(8.0)
        ->and(ligne($sansMagasin, '2026-01-04')['sick_paid_hours'])->toBe(0.0);
});

it('compte les maladies déjà payées plus tôt dans l\'année de maladie', function () {
    $store = Store::factory()->create(['sick_accrual_start' => '01-01', 'sick_days_full_time' => 2]);
    $user = User::factory()->create(['store_id' => $store->id, 'is_full_time' => true]);

    quart($user, '2026-01-05', ScheduleType::Sick);
    quart($user, '2026-01-20', ScheduleType::Sick);
    quart($user, '2026-01-21', ScheduleType::Sick);

    expect(ligne($user, '2026-01-18')['sick_paid_hours'])->toBe(8.0);
});

it('inclut les employés actifs sans horaire et exclut ceux pas encore en fonction', function () {
    $actif = User::factory()->create(['first_day' => '2025-06-01']);
    $futur = User::factory()->create(['first_day' => '2026-03-01']);

    $ids = app(BuildPayrollReport::class)->handle(Carbon::parse('2026-01-04'))->pluck('user.id');

    expect($ids)->toContain($actif->id)->not->toContain($futur->id);
});

// ── Export ─────────────────────────────────────────────────────────────────

it('exige le verrouillage avant de générer le rapport', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Payroll::class)
        ->set('periodStart', '2026-01-04')
        ->call('export')
        ->assertNoFileDownloaded();
});

it('génère le rapport CSV de la période verrouillée', function () {
    $user = User::factory()->create(['firstname' => 'Alice', 'lastname' => 'Tremblay', 'first_day' => '2025-01-01']);
    quart($user, '2026-01-05');
    quart($user, '2026-01-06', ScheduleType::Training);

    $admin = User::factory()->withRole('admin')->create(['first_day' => '2027-01-01']);
    app(LockPayrollPeriod::class)->lock(Carbon::parse('2026-01-04'), $admin);

    $this->actingAs($admin);

    $expected = "\u{FEFF}"
        ."Employé;\"Heures travaillées\";\"Maladie payée (h)\";\"Formation (h)\";\"Vacances à payer (h)\";\"Primes à verser ($)\";\"Commissions à verser ($)\"\n"
        ."\"Tremblay, Alice\";7,50;0,00;7,50;0,00;0,00;0,00\n";

    Livewire::test(Payroll::class)
        ->set('periodStart', '2026-01-04')
        ->call('export')
        ->assertFileDownloaded('paie_2026-01-04_2026-01-17.csv', $expected);
});

// ── Magasin de l'employé ───────────────────────────────────────────────────

it('prend le début de l\'année de vacances dans le magasin de l\'employé', function () {
    $store = Store::factory()->create(['vacation_accrual_start' => '01-01']);
    $user = User::factory()->create(['store_id' => $store->id, 'first_day' => '2020-01-15']);

    $calculator = app(CalculateVacationBalance::class);

    expect($calculator->referenceYearStart(Carbon::parse('2026-10-31'), $user->fresh())->toDateString())->toBe('2026-01-01')
        ->and($calculator->referenceYearStart(Carbon::parse('2026-10-31'), User::factory()->create())->toDateString())->toBe('2026-05-01');
});

it('assigne le magasin de l\'employé sur sa fiche', function () {
    $store = Store::factory()->create();
    $user = User::factory()->create(['first_day' => '2025-01-01', 'cellphone' => '(514)555-1234']);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('storeId', $store->id)
        ->call('saveIdentification')
        ->assertHasNoErrors();

    expect($user->fresh()->store_id)->toBe($store->id);
});

it('enregistre une formation avec ses heures à l\'horaire', function () {
    $this->travelTo(Carbon::parse('2026-01-01'));
    $user = User::factory()->create(['first_day' => '2025-01-01']);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(ScheduleEdit::class)
        ->call('openCell', $user->id, '2026-01-06')
        ->set('scheduleType', 'training')
        ->set('startTime', '09:00')
        ->set('endTime', '12:00')
        ->set('breakMinutes', 0)
        ->call('save')
        ->assertHasNoErrors();

    $schedule = Schedule::where('user_id', $user->id)->first();

    expect($schedule->type)->toBe(ScheduleType::Training)
        ->and($schedule->start_time)->toBe('09:00');
});
