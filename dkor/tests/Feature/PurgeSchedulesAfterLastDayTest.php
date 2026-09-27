<?php

use App\Actions\PurgeSchedulesAfterLastDay;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;

it('supprime les quarts draft et published après le last_day', function () {
    $user = User::factory()->create(['last_day' => '2026-09-20']);

    Schedule::factory()->create(['user_id' => $user->id, 'date' => '2026-09-21', 'status' => ScheduleStatus::Draft]);
    Schedule::factory()->create(['user_id' => $user->id, 'date' => '2026-09-22', 'status' => ScheduleStatus::Published]);

    (new PurgeSchedulesAfterLastDay)->execute($user);

    expect(Schedule::where('user_id', $user->id)->count())->toBe(0);
});

it('ne supprime pas les quarts le jour du last_day ou avant', function () {
    $user = User::factory()->create(['last_day' => '2026-09-20']);

    Schedule::factory()->create(['user_id' => $user->id, 'date' => '2026-09-20', 'status' => ScheduleStatus::Draft]);
    Schedule::factory()->create(['user_id' => $user->id, 'date' => '2026-09-19', 'status' => ScheduleStatus::Published]);

    (new PurgeSchedulesAfterLastDay)->execute($user);

    expect(Schedule::where('user_id', $user->id)->count())->toBe(2);
});

it('ne supprime pas les quarts avec un statut non modifiable après le last_day', function () {
    $user = User::factory()->create(['last_day' => '2026-09-20']);

    Schedule::factory()->create(['user_id' => $user->id, 'date' => '2026-09-25', 'status' => ScheduleStatus::Closed]);
    Schedule::factory()->create(['user_id' => $user->id, 'date' => '2026-09-26', 'status' => ScheduleStatus::Paid]);

    (new PurgeSchedulesAfterLastDay)->execute($user);

    expect(Schedule::where('user_id', $user->id)->count())->toBe(2);
});

it('ne fait rien si l\'employé n\'a pas de last_day', function () {
    $user = User::factory()->create(['last_day' => null]);

    Schedule::factory()->create(['user_id' => $user->id, 'date' => '2026-09-25', 'status' => ScheduleStatus::Draft]);

    (new PurgeSchedulesAfterLastDay)->execute($user);

    expect(Schedule::where('user_id', $user->id)->count())->toBe(1);
});
