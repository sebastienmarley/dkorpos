<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Mon horaire') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ ucfirst($startDate->translatedFormat('d F')) }} – {{ $endDate->translatedFormat('d F Y') }}
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            @if (! $isCurrentWeek)
                <flux:button size="sm" variant="ghost" wire:click="goToCurrentWeek">
                    {{ __('Semaine actuelle') }}
                </flux:button>
            @endif

            <flux:button.group>
                <flux:button size="sm" icon="chevron-left" wire:click="previousWeek" :label="__('Semaine précédente')" />
                <flux:button size="sm" icon="chevron-right" wire:click="nextWeek" :label="__('Semaine suivante')" />
            </flux:button.group>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Jour') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Début') }}</flux:table.column>
                <flux:table.column>{{ __('Fin') }}</flux:table.column>
                <flux:table.column>{{ __('Durée') }}</flux:table.column>
                <flux:table.column>{{ __('Notes') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($days as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $schedule = $schedules->get($dateKey);
                        $isToday = $day->isToday();
                        $isWeekend = $day->isWeekend();

                        $duration = null;
                        if ($schedule && $schedule->start_time && $schedule->end_time) {
                            $start = \Carbon\Carbon::parse($schedule->start_time);
                            $end = \Carbon\Carbon::parse($schedule->end_time);
                            $minutes = max(0, $start->diffInMinutes($end) - $schedule->break_minutes);
                            $duration = sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
                        }
                    @endphp

                    <flux:table.row :key="$dateKey" @class(['opacity-50' => $isWeekend && ! $schedule])>
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-2">
                                @if ($isToday)
                                    <flux:badge size="sm" color="blue" inset="top bottom">{{ __("Aujourd'hui") }}</flux:badge>
                                @endif
                                {{ ucfirst($day->translatedFormat('l')) }}
                                @if ($holiday = $holidays->get($dateKey))
                                    <flux:badge size="sm" color="violet" inset="top bottom">{{ $holiday->name }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>{{ $day->translatedFormat('d M') }}</flux:table.cell>

                        @if ($schedule?->type->isAbsence())
                            <flux:table.cell colspan="2">
                                <flux:badge size="sm" :color="$schedule->type->color()" inset="top bottom">{{ $schedule->type->label() }}</flux:badge>
                            </flux:table.cell>
                        @else
                            <flux:table.cell>
                                {{ $schedule?->start_time ? \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) : '—' }}
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $schedule?->end_time ? \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) : '—' }}
                            </flux:table.cell>
                        @endif

                        <flux:table.cell>
                            @if ($duration)
                                <flux:badge size="sm" color="green" inset="top bottom">{{ $duration }}</flux:badge>
                            @else
                                —
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $schedule?->notes ?: '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
