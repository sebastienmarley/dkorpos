<?php

namespace App\Livewire\Schedules;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class ScheduleEdit extends Component
{
    public string $weekStart;

    public bool $showModal = false;

    public ?int $editingUserId = null;

    public string $editingDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public int $breakMinutes = 0;

    public string $status = 'draft';

    public string $notes = '';

    public function mount(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function goToCurrentWeek(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();
    }

    public function openCell(int $userId, string $date): void
    {
        $this->editingUserId = $userId;
        $this->editingDate = $date;

        $schedule = Schedule::query()
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();

        $this->startTime = $schedule?->start_time ? substr($schedule->start_time, 0, 5) : '';
        $this->endTime = $schedule?->end_time ? substr($schedule->end_time, 0, 5) : '';
        $this->breakMinutes = $schedule ? $schedule->break_minutes : 0;
        $this->status = $schedule ? $schedule->status->value : ScheduleStatus::Draft->value;
        $this->notes = $schedule ? ($schedule->notes ?? '') : '';

        $this->showModal = true;
    }

    public function publishWeek(): void
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        Schedule::query()
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $start->copy()->addDays(7)->toDateString())
            ->update(['status' => ScheduleStatus::Published]);
    }

    public function unpublishWeek(): void
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        Schedule::query()
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $start->copy()->addDays(7)->toDateString())
            ->whereIn('status', [ScheduleStatus::Published->value, ScheduleStatus::Draft->value])
            ->update(['status' => ScheduleStatus::Draft]);
    }

    public function deleteSchedule(): void
    {
        Schedule::query()
            ->where('user_id', $this->editingUserId)
            ->whereDate('date', $this->editingDate)
            ->delete();

        $this->showModal = false;
        $this->reset(['editingUserId', 'editingDate', 'startTime', 'endTime', 'breakMinutes', 'notes']);
    }

    public function save(): void
    {
        $this->validate([
            'startTime' => ['nullable', 'date_format:H:i', 'required_with:endTime'],
            'endTime' => ['nullable', 'date_format:H:i', 'after:startTime', 'required_with:startTime'],
            'breakMinutes' => ['required', 'integer', 'in:0,30,60'],
            'status' => ['required', 'string', 'in:draft,published,closed,paid'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'startTime.required_with' => __("L'heure de début est requise si une heure de fin est saisie."),
            'endTime.required_with' => __("L'heure de fin est requise si une heure de début est saisie."),
        ]);

        $employee = User::findOrFail($this->editingUserId);

        if (! $employee->first_day) {
            $this->addError('editingDate', __("Impossible d'assigner un quart : l'employé n'a pas de date d'entrée en fonction."));

            return;
        }

        if ($this->editingDate < $employee->first_day->toDateString()) {
            $this->addError('editingDate', __("La date est antérieure au premier jour de travail de l'employé (:date).", [
                'date' => $employee->first_day->translatedFormat('d F Y'),
            ]));

            return;
        }

        if ($employee->last_day && $this->editingDate > $employee->last_day->toDateString()) {
            $this->addError('editingDate', __("La date est postérieure au dernier jour de travail de l'employé (:date).", [
                'date' => $employee->last_day->translatedFormat('d F Y'),
            ]));

            return;
        }

        $existingSchedule = Schedule::query()
            ->where('user_id', $this->editingUserId)
            ->whereDate('date', $this->editingDate)
            ->first();

        if ($existingSchedule && ! in_array($existingSchedule->status, ScheduleStatus::editableValues())) {
            $this->addError('editingDate', __('Ce quart ne peut pas être modifié (statut : :status).', [
                'status' => $existingSchedule->status->label(),
            ]));

            return;
        }

        $data = [
            'start_time' => filled($this->startTime) ? $this->startTime : null,
            'end_time' => filled($this->endTime) ? $this->endTime : null,
            'break_minutes' => $this->breakMinutes,
            'status' => $this->status,
            'notes' => filled($this->notes) ? $this->notes : null,
        ];

        if ($existingSchedule) {
            $existingSchedule->update($data);
        } else {
            Schedule::create(['user_id' => $this->editingUserId, 'date' => $this->editingDate, ...$data]);
        }

        $this->showModal = false;
        $this->reset(['editingUserId', 'editingDate', 'startTime', 'endTime', 'breakMinutes', 'status', 'notes']);
    }

    public function render(): View
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        $days = Collection::times(7, fn ($i) => $start->copy()->addDays($i - 1));

        $userIds = User::query()->where('is_active', true)->orderBy('id')->pluck('id');

        $schedules = Schedule::query()
            ->whereIn('user_id', $userIds)
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $start->copy()->addDays(7)->toDateString())
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($s) => Carbon::parse($s->date)->toDateString()));

        $users = User::query()->where('is_active', true)->orderBy('id')->get();

        $totalHours = $schedules->map(function ($userSchedules) {
            return $userSchedules->sum(function ($schedule) {
                if (! $schedule->start_time || ! $schedule->end_time) {
                    return 0;
                }

                $worked = Carbon::parse($schedule->start_time)->diffInMinutes(Carbon::parse($schedule->end_time));

                return max(0, $worked - $schedule->break_minutes);
            });
        });

        return view('livewire.schedules.schedule-edit', [
            'users' => $users,
            'days' => $days,
            'schedules' => $schedules,
            'totalHours' => $totalHours,
            'startDate' => $start,
            'endDate' => $start->copy()->addDays(6),
            'isCurrentWeek' => $start->isSameWeek(Carbon::now()),
        ])->layout('layouts.app', ['title' => __('Gestion des horaires')]);
    }
}
