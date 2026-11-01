<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_date' => today()->toDateString(),
            'description' => fake()->sentence(3),
            'document_number' => null,
            'store_id' => null,
            'reverses_id' => null,
            'created_by' => null,
        ];
    }
}
