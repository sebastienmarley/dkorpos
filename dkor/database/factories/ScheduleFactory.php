<?php

namespace Database\Factories;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_minutes' => 30,
            'status' => ScheduleStatus::Draft,
            'notes' => null,
        ];
    }

    public function forDate(string $date): static
    {
        return $this->state(['date' => $date]);
    }

    public function withNotes(string $notes): static
    {
        return $this->state(['notes' => $notes]);
    }

    public function withoutHours(): static
    {
        return $this->state(['start_time' => null, 'end_time' => null, 'break_minutes' => 0]);
    }

    public function published(): static
    {
        return $this->state(['status' => ScheduleStatus::Published]);
    }

    public function withStatus(ScheduleStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
