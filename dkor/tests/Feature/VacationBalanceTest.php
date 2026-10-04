<?php

use App\Actions\CalculateVacationBalance;
use App\Enums\ScheduleType;
use App\Livewire\Users\Show;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function vacances(User $user, string ...$dates): void
{
    foreach ($dates as $date) {
        Schedule::factory()->for($user)->forDate($date)->absence(ScheduleType::Vacation)->create();
    }
}

function solde(User $user, string $date): array
{
    return app(CalculateVacationBalance::class)->calculate($user->fresh(), Carbon::parse($date));
}

it('commence l\'année de référence le 1er mai', function () {
    $calculator = app(CalculateVacationBalance::class);

    expect($calculator->referenceYearStart(Carbon::parse('2026-10-31'))->toDateString())->toBe('2026-05-01')
        ->and($calculator->referenceYearStart(Carbon::parse('2027-04-30'))->toDateString())->toBe('2026-05-01')
        ->and($calculator->referenceYearStart(Carbon::parse('2027-05-01'))->toDateString())->toBe('2027-05-01');
});

it('accorde 3 semaines par année, au prorata, à partir de 3 ans d\'ancienneté', function () {
    $user = User::factory()->create(['first_day' => '2020-01-15']);

    // Du 1er mai au 31 octobre inclus : 184 jours sur 365.
    $balance = solde($user, '2026-10-31');

    expect($balance['days_accrued'])->toBe(7.56)
        ->and($balance['hours_accrued'])->toBe(60.48)
        ->and($balance['hours_available'])->toBe(60.48);
});

it('accorde 2 semaines par année, au prorata, entre 1 et 3 ans d\'ancienneté', function () {
    $user = User::factory()->create(['first_day' => '2025-01-15']);

    expect(solde($user, '2026-10-31')['days_accrued'])->toBe(5.04);
});

it('accorde 1 jour par mois la première année', function () {
    $user = User::factory()->create(['first_day' => '2026-08-01']);

    // Du 1er août au 31 octobre inclus : 92 jours → 92 × 12 / 365.
    expect(solde($user, '2026-10-31')['days_accrued'])->toBe(3.02);
});

it('plafonne la première année à 2 semaines', function () {
    $user = User::factory()->create(['first_day' => '2026-05-01']);

    expect(solde($user, '2027-03-31')['days_accrued'])->toBe(10.0);
});

it('donne le droit complet à la fin de l\'année de référence', function () {
    $user = User::factory()->create(['first_day' => '2018-03-01']);

    expect(solde($user, '2027-04-30')['days_accrued'])->toBe(15.0);
});

it('donne un solde négatif quand l\'employé prend ses vacances en début d\'année', function () {
    $user = User::factory()->create(['first_day' => '2020-01-15']);
    vacances($user, '2026-05-04', '2026-05-05', '2026-05-06', '2026-05-07', '2026-05-08');

    $balance = solde($user, '2026-05-15');

    // 15 jours écoulés : 15 × 15 / 365 = 0,62 jour, soit 4,96 h acquises pour 40 h prises.
    expect($balance['days_taken'])->toBe(5)
        ->and($balance['hours_taken'])->toBe(40.0)
        ->and($balance['hours_available'])->toBe(-35.04);
});

it('ne compte que les vacances de l\'année de référence déjà passées', function () {
    $user = User::factory()->create(['first_day' => '2020-01-15']);
    vacances($user, '2026-04-20', '2026-05-04', '2026-06-15');

    expect(solde($user, '2026-05-10')['days_taken'])->toBe(1);
});

it('utilise les heures par jour de l\'employé', function () {
    $user = User::factory()->create(['first_day' => '2020-01-15', 'hours_per_day' => '7.50']);
    vacances($user, '2026-05-04');

    $balance = solde($user, '2026-10-31');

    expect($balance['hours_per_day'])->toBe(7.5)
        ->and($balance['hours_accrued'])->toBe(56.7)
        ->and($balance['hours_available'])->toBe(49.2);
});

it('arrête l\'acquisition au dernier jour de l\'employé', function () {
    $user = User::factory()->create(['first_day' => '2020-01-15', 'last_day' => '2026-05-31']);

    expect(solde($user, '2026-10-31')['days_accrued'])->toBe(solde($user, '2026-05-31')['days_accrued']);
});

it('n\'accorde rien sans premier jour ou avant l\'embauche', function () {
    expect(solde(User::factory()->create(['first_day' => null]), '2026-10-31')['days_accrued'])->toBe(0.0)
        ->and(solde(User::factory()->create(['first_day' => '2026-12-01']), '2026-10-31')['days_accrued'])->toBe(0.0);
});

it('met à jour le solde enregistré quand une journée de vacances est ajoutée, modifiée ou retirée', function () {
    $this->travelTo(Carbon::parse('2026-10-31 12:00'));

    $user = User::factory()->create(['first_day' => '2020-01-15']);
    $schedule = Schedule::factory()->for($user)->forDate('2026-10-01')->absence(ScheduleType::Vacation)->create();

    expect($user->fresh()->vacation_hours_available)->toBe('52.48');

    $schedule->update(['type' => ScheduleType::Work, 'start_time' => '09:00', 'end_time' => '17:00']);
    expect($user->fresh()->vacation_hours_available)->toBe('60.48');

    $schedule->update(['type' => ScheduleType::Vacation, 'start_time' => null, 'end_time' => null]);
    $schedule->delete();
    expect($user->fresh()->vacation_hours_available)->toBe('60.48')
        ->and($user->fresh()->vacation_days_accrued)->toBe('7.56');
});

it('cache les heures disponibles à la sérialisation', function () {
    $user = User::factory()->create(['first_day' => '2020-01-15']);
    app(CalculateVacationBalance::class)->refresh($user);

    expect($user->fresh()->toArray())->not->toHaveKey('vacation_hours_available');
});

it('recalcule les soldes des employés actifs chaque jour', function () {
    $this->travelTo(Carbon::parse('2026-10-31 00:05'));

    $actif = User::factory()->create(['first_day' => '2020-01-15']);
    $inactif = User::factory()->create(['first_day' => '2020-01-15', 'is_active' => false]);

    $this->artisan('vacations:refresh')->assertSuccessful();

    expect($actif->fresh()->vacation_days_accrued)->toBe('7.56')
        ->and($inactif->fresh()->vacation_days_accrued)->toBeNull();
});

it('recalcule le solde quand le premier jour change sur la fiche', function () {
    $this->travelTo(Carbon::parse('2026-10-31 12:00'));

    $user = User::factory()->create([
        'first_day' => '2020-01-15', 'personal_email' => null, 'cellphone' => '(514)555-1234',
    ]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('firstDay', '2025-01-15')
        ->call('saveIdentification')
        ->assertHasNoErrors();

    expect($user->fresh()->vacation_days_accrued)->toBe('5.04');
});

it('reporte un solde négatif sur l\'année de référence suivante', function () {
    $user = User::factory()->create(['first_day' => '2026-03-02']);

    // Année 2025-2026 : du 2 mars au 30 avril, 60 jours → 60 × 12 / 365 = 1,97 jour (15,76 h).
    // 5 jours de vacances pris (40 h) : solde de fin d'année = −24,24 h, reporté.
    vacances($user, '2026-04-06', '2026-04-07', '2026-04-08', '2026-04-09', '2026-04-10');

    $balance = solde($user, '2026-05-01');

    // 1er mai : 1 jour écoulé → 1 × 12 / 365 = 0,03 jour (0,24 h).
    expect($balance['hours_carried_over'])->toBe(-24.24)
        ->and($balance['days_taken'])->toBe(0)
        ->and($balance['hours_available'])->toBe(-24.0);
});

it('ne reporte pas un solde positif', function () {
    $user = User::factory()->create(['first_day' => '2020-01-15']);

    $balance = solde($user, '2026-05-01');

    expect($balance['hours_carried_over'])->toBe(0.0)
        ->and($balance['hours_available'])->toBe($balance['hours_accrued']);
});

it('accumule le report négatif sur plusieurs années', function () {
    $user = User::factory()->create(['first_day' => '2023-05-01']);

    // 2023-2024 (moins d'un an) : 10 jours acquis, 15 pris → −40 h.
    vacances($user, ...array_map(fn ($d) => sprintf('2023-06-%02d', $d), range(5, 19)));

    // 2024-2025 (1 an) : 10 jours acquis (80 h) + report −40 h, 16 pris (128 h) → −88 h.
    vacances($user, ...array_map(fn ($d) => sprintf('2024-07-%02d', $d), range(1, 16)));

    expect(solde($user, '2025-05-01')['hours_carried_over'])->toBe(-88.0);

    // 2025-2026 : 10 jours (80 h) + report −88 h, rien pris → −8 h reporté au 1er mai 2026.
    expect(solde($user, '2026-05-01')['hours_carried_over'])->toBe(-8.0);
});

it('rembourse le report négatif au fil de la nouvelle année', function () {
    $user = User::factory()->create(['first_day' => '2026-03-02']);
    vacances($user, '2026-04-06', '2026-04-07', '2026-04-08', '2026-04-09', '2026-04-10');

    // 31 octobre : 184 jours → 6,05 jours (48,4 h) − 24,24 h reportées.
    expect(solde($user, '2026-10-31')['hours_available'])->toBe(24.16);
});
