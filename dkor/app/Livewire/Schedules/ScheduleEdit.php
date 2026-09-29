<?php

namespace App\Livewire\Schedules;

use App\Actions\FillWeekSchedules;
use App\Enums\ScheduleStatus;
use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WeekTemplate;
use App\Models\WeekTemplateEntry;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ScheduleEdit extends Component
{
    public string $weekStart;

    public bool $showModal = false;

    public ?int $editingUserId = null;

    public ?int $editingScheduleId = null;

    /** Horodatage updated_at du quart à l'ouverture, pour détecter une modification concurrente. */
    public int $editingVersion = 0;

    public string $editingDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public int $breakMinutes = 0;

    public string $status = 'draft';

    public string $notes = '';

    public string $shiftTemplateId = '';

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

        $this->editingScheduleId = $schedule?->id;
        $this->editingVersion = $schedule?->updated_at?->getTimestamp() ?? 0;
        $this->startTime = $schedule?->start_time ? substr($schedule->start_time, 0, 5) : '';
        $this->endTime = $schedule?->end_time ? substr($schedule->end_time, 0, 5) : '';
        $this->breakMinutes = $schedule ? $schedule->break_minutes : 0;
        $this->status = $schedule ? $schedule->status->value : ScheduleStatus::Draft->value;
        $this->notes = $schedule ? ($schedule->notes ?? '') : '';
        $this->shiftTemplateId = '';

        $this->showModal = true;
    }

    /** Remplit les heures et la pause à partir du quart type choisi. */
    public function updatedShiftTemplateId(string $value): void
    {
        $template = filled($value) ? ShiftTemplate::query()->find($value) : null;

        if (! $template) {
            return;
        }

        $this->startTime = substr($template->start_time, 0, 5);
        $this->endTime = substr($template->end_time, 0, 5);
        $this->breakMinutes = $template->break_minutes;
    }

    /** Copie les quarts de la semaine précédente vers la semaine affichée, en brouillon. */
    public function copyPreviousWeek(): void
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        $planned = Schedule::query()
            ->where('date', '>=', $start->copy()->subWeek()->toDateString())
            ->where('date', '<', $start->toDateString())
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'user_id' => $schedule->user_id,
                'date' => Carbon::parse($schedule->date)->addWeek()->toDateString(),
                'start_time' => (string) $schedule->start_time,
                'end_time' => (string) $schedule->end_time,
                'break_minutes' => $schedule->break_minutes,
                'notes' => $schedule->notes,
            ]);

        $this->reportFill(app(FillWeekSchedules::class)->handle($planned), __('Copie de la semaine précédente'));
    }

    /** Applique une semaine type à la semaine affichée, en brouillon. */
    public function applyWeekTemplate(int $weekTemplateId): void
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        $planned = WeekTemplateEntry::query()
            ->where('week_template_id', $weekTemplateId)
            ->with('shiftTemplate')
            ->get()
            ->map(fn (WeekTemplateEntry $entry) => [
                'user_id' => $entry->user_id,
                'date' => $start->copy()->addDays($entry->weekday)->toDateString(),
                'start_time' => $entry->shiftTemplate->start_time,
                'end_time' => $entry->shiftTemplate->end_time,
                'break_minutes' => $entry->shiftTemplate->break_minutes,
            ]);

        $weekTemplate = WeekTemplate::query()->find($weekTemplateId);

        $this->reportFill(app(FillWeekSchedules::class)->handle($planned), __('Semaine type « :name »', ['name' => $weekTemplate ? $weekTemplate->name : '']));
    }

    /**
     * @param  array{created: int, existing: int, unavailable: int, conflicts: int}  $result
     */
    private function reportFill(array $result, string $heading): void
    {
        $lines = [trans_choice(':count quart créé en brouillon.|:count quarts créés en brouillon.', $result['created'])];

        if ($result['existing'] > 0) {
            $lines[] = trans_choice(':count case ignorée (quart déjà présent).|:count cases ignorées (quart déjà présent).', $result['existing']);
        }

        if ($result['unavailable'] > 0) {
            $lines[] = trans_choice(':count case ignorée (employé inactif ou hors de sa période de travail).|:count cases ignorées (employé inactif ou hors de sa période de travail).', $result['unavailable']);
        }

        if ($result['conflicts'] > 0) {
            $lines[] = trans_choice(':count case ignorée (rendez-vous déjà pris hors de ce quart).|:count cases ignorées (rendez-vous déjà pris hors de ce quart).', $result['conflicts']);
        }

        Flux::toast(
            text: implode(' ', $lines),
            heading: $heading,
            variant: $result['created'] > 0 ? 'success' : 'warning',
        );
    }

    public function publishWeek(): void
    {
        $this->activeWeekSchedules()
            ->where('status', ScheduleStatus::Draft->value)
            ->update(['status' => ScheduleStatus::Published, 'last_updated_by' => auth()->user()?->id]);
    }

    public function unpublishWeek(): void
    {
        $publishedIds = $this->activeWeekSchedules()
            ->where('status', ScheduleStatus::Published->value)
            ->pluck('id');

        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        $bookedCount = Appointment::query()
            ->where('date', '>=', max($start->toDateString(), Carbon::today()->toDateString()))
            ->where('date', '<', $start->copy()->addDays(7)->toDateString())
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('schedules')
                ->whereColumn('schedules.user_id', 'appointments.user_id')
                ->whereColumn('schedules.date', 'appointments.date')
                ->whereIn('schedules.id', $publishedIds))
            ->count();

        if ($bookedCount > 0) {
            Flux::toast(
                text: trans_choice(':count rendez-vous est pris pendant cette semaine. Déplacez-le avant de dépublier.|:count rendez-vous sont pris pendant cette semaine. Déplacez-les avant de dépublier.', $bookedCount),
                heading: __('Dépublication refusée'),
                variant: 'danger',
            );

            return;
        }

        Schedule::query()
            ->whereIn('id', $publishedIds)
            ->update(['status' => ScheduleStatus::Draft, 'last_updated_by' => auth()->user()?->id]);
    }

    public function deleteSchedule(): void
    {
        $schedule = Schedule::query()
            ->where('user_id', $this->editingUserId)
            ->whereDate('date', $this->editingDate)
            ->first();

        if ($schedule) {
            if (! in_array($schedule->status, ScheduleStatus::editableValues())) {
                $this->addError('editingDate', __('Ce quart ne peut pas être supprimé (statut : :status).', [
                    'status' => $schedule->status->label(),
                ]));

                return;
            }

            $conflicts = Appointment::countOutsideWindow((int) $this->editingUserId, $this->editingDate, null);

            if ($conflicts > 0) {
                $this->addError('editingDate', $this->conflictMessage($conflicts));

                return;
            }

            $schedule->delete();
        }

        $this->closeModal();
    }

    public function save(): void
    {
        $current = $this->editingScheduleId ? Schedule::query()->find($this->editingScheduleId) : null;

        if ($current && ! in_array($current->status, ScheduleStatus::editableValues())) {
            $this->addError('editingDate', __('Ce quart ne peut pas être modifié (statut : :status).', [
                'status' => $current->status->label(),
            ]));

            return;
        }

        $this->validate([
            'startTime' => ['nullable', 'date_format:H:i', 'required_with:endTime'],
            'endTime' => ['nullable', 'date_format:H:i', 'after:startTime', 'required_with:startTime'],
            'breakMinutes' => ['required', 'integer', 'in:0,30,60'],
            'status' => ['required', 'string', Rule::in(array_map(fn (ScheduleStatus $status) => $status->value, ScheduleStatus::editableValues()))],
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

        $data = [
            'start_time' => filled($this->startTime) ? $this->startTime : null,
            'end_time' => filled($this->endTime) ? $this->endTime : null,
            'break_minutes' => $this->breakMinutes,
            'status' => $this->status,
            'notes' => filled($this->notes) ? $this->notes : null,
        ];

        $error = $this->writeSchedule($data);

        if ($error !== null) {
            $this->addError('editingDate', $error);

            return;
        }

        $this->closeModal();
    }

    /**
     * Écrit le quart sous verrou (sérialisé par employé) après avoir revérifié qu'il n'a pas changé
     * depuis l'ouverture de la fenêtre et qu'aucun rendez-vous ne se retrouve hors horaire.
     *
     * @param  array<string, mixed>  $data
     * @return string|null Message d'erreur, ou null si l'écriture a eu lieu.
     */
    private function writeSchedule(array $data): ?string
    {
        return DB::transaction(function () use ($data): ?string {
            User::query()->whereKey($this->editingUserId)->lockForUpdate()->first();

            $existing = Schedule::query()
                ->where('user_id', $this->editingUserId)
                ->whereDate('date', $this->editingDate)
                ->first();

            if ($this->editingScheduleId !== null && ! $existing) {
                return __('Ce quart a été supprimé par un autre usager.');
            }

            if ($existing && ($existing->id !== $this->editingScheduleId || $existing->updated_at?->getTimestamp() !== $this->editingVersion)) {
                $editor = $existing->lastUpdatedBy?->fullName();

                return $editor
                    ? __('Ce quart a été modifié entre-temps par :name. Fermez et rouvrez la fenêtre pour voir les changements.', ['name' => $editor])
                    : __('Ce quart a été modifié entre-temps. Fermez et rouvrez la fenêtre pour voir les changements.');
            }

            if ($existing && ! in_array($existing->status, ScheduleStatus::editableValues())) {
                return __('Ce quart ne peut pas être modifié (statut : :status).', [
                    'status' => $existing->status->label(),
                ]);
            }

            $window = $data['status'] === ScheduleStatus::Draft->value || ! $data['start_time'] || ! $data['end_time']
                ? null
                : [$this->minutesOf($data['start_time']), $this->minutesOf($data['end_time'])];

            $conflicts = Appointment::countOutsideWindow((int) $this->editingUserId, $this->editingDate, $window);

            if ($conflicts > 0) {
                return $this->conflictMessage($conflicts);
            }

            if ($existing) {
                $existing->update($data);
            } else {
                Schedule::create(['user_id' => $this->editingUserId, 'date' => $this->editingDate, ...$data]);
            }

            return null;
        });
    }

    private function conflictMessage(int $count): string
    {
        return trans_choice(
            ':count rendez-vous est déjà pris en dehors de cet horaire. Déplacez-le d\'abord.|:count rendez-vous sont déjà pris en dehors de cet horaire. Déplacez-les d\'abord.',
            $count,
        );
    }

    private function minutesOf(string $time): int
    {
        $parsed = Carbon::parse($time);

        return $parsed->hour * 60 + $parsed->minute;
    }

    /**
     * Quarts de la semaine affichée, pour les employés actifs seulement.
     *
     * @return Builder<Schedule>
     */
    private function activeWeekSchedules(): Builder
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        return Schedule::query()
            ->whereIn('user_id', User::query()->where('is_active', true)->select('id'))
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $start->copy()->addDays(7)->toDateString());
    }

    private function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
        $this->reset(['editingUserId', 'editingScheduleId', 'editingVersion', 'editingDate', 'startTime', 'endTime', 'breakMinutes', 'status', 'notes', 'shiftTemplateId']);
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

        $editingSchedule = $this->showModal && $this->editingScheduleId
            ? Schedule::query()->with(['creator', 'lastUpdatedBy'])->find($this->editingScheduleId)
            : null;

        return view('livewire.schedules.schedule-edit', [
            'shiftTemplates' => ShiftTemplate::query()->orderBy('name')->get(),
            'weekTemplates' => WeekTemplate::query()->orderBy('name')->get(),
            'editingSchedule' => $editingSchedule,
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
