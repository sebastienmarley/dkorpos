<?php

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('scheduled tasks run at the store local time', function () {
    $applyPriceLists = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains($event->command, 'price-lists:apply'));

    $this->travelTo(CarbonImmutable::parse('2026-07-15 09:00', 'UTC'));

    expect($applyPriceLists->isDue(app()))->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2026-07-15 05:00', 'UTC'));

    expect($applyPriceLists->isDue(app()))->toBeFalse();
});
