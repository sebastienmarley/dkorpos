<?php

namespace App\Livewire\Schedules;

use App\Actions\FillWeekSchedules;
use App\Actions\ReassignAppointment;
use App\Enums\ScheduleStatus;
use App\Enums\ScheduleType;
use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\PayrollPeriod;
use App\Models\Schedule;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WeekTemplate;
use App\Models\WeekTemplateEntry;
use App\Support\AppointmentBooking;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
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

    public string $scheduleType = 'work';

    /** Dernier jour d'une absence de plusieurs jours (optionnel). */
    public string $untilDate = '';

    public bool $showReassignModal = false;

    public ?int $reassigningAppointmentId = null;

    /** Horodatage updated_at du rendez-vous à l'ouverture, pour détecter une modification concurrente. */
    public int $reassignVersion = 0;

    public ?int $reassignUserId = null;

    public string $reassignDate = '';

    public int $reassignStartMinute = AppointmentBooking::DAY_START_MINUTE;

    /** Nombre de quarts de la semaine affichée à la dernière synchronisation, pour détecter les modifications des autres. */
    #[Locked]
    public int $weekShiftCount = 0;

    /** Horodatage de la dernière modification d'un quart de la semaine affichée, à la dernière synchronisation. */
    #[Locked]
    public int $weekLatestUpdate = 0;

    public function mount(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();
        $this->syncWeekSnapshot();
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
        $this->syncWeekSnapshot();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
        $this->syncWeekSnapshot();
    }

    public function goToCurrentWeek(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();
        $this->syncWeekSnapshot();
    }

    public function updatedWeekStart(): void
    {
        $this->syncWeekSnapshot();
    }

    /** Appelée périodiquement : ne redessine la page que si quelqu'un d'autre a modifié la semaine affichée. */
    public function checkForChanges(): void
    {
        if ($this->isWeekSnapshotCurrent()) {
            $this->skipRender();
        }
    }

    /** L'usager a pris connaissance des modifications : la grille affichée devient la référence. */
    public function refreshWeek(): void
    {
        $this->syncWeekSnapshot();
    }

    public function openCell(int $userId, string $date): void
    {
        $this->authorize('schedule_management.edit');

        if (PayrollPeriod::isDateLocked($date)) {
            Flux::toast(text: __('Cette journée fait partie d\'une période de paie verrouillée.'), heading: __('Horaire verrouillé'), variant: 'danger');

            return;
        }

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
        $this->scheduleType = $schedule ? $schedule->type->value : ScheduleType::Work->value;
        $this->untilDate = '';

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
        $this->authorize('schedule_management.edit');

        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        $planned = Schedule::query()
            ->where('date', '>=', $start->copy()->subWeek()->toDateString())
            ->where('date', '<', $start->toDateString())
            ->where('type', ScheduleType::Work->value)
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

        $result = DB::transaction(function () use ($planned): ?array {
            $this->lockActiveEmployees();

            return $this->refuseIfWeekChanged() ? null : app(FillWeekSchedules::class)->handle($planned);
        });

        if ($result === null) {
            return;
        }

        $this->syncWeekSnapshot();
        $this->reportFill($result, __('Copie de la semaine précédente'));
    }

    /** Applique une semaine type à la semaine affichée, en brouillon. */
    public function applyWeekTemplate(int $weekTemplateId): void
    {
        $this->authorize('schedule_management.edit');

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
        $weekWasCurrent = $this->isWeekSnapshotCurrent();

        $result = app(FillWeekSchedules::class)->handle($planned);
        $this->syncAfterOwnWrite($weekWasCurrent);

        $this->reportFill($result, __('Semaine type « :name »', ['name' => $weekTemplate ? $weekTemplate->name : '']));
    }

    /**
     * @param  array{created: int, existing: int, unavailable: int, conflicts: int, holidays: int}  $result
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

        if ($result['holidays'] > 0) {
            $lines[] = trans_choice(':count case ignorée (férié fermé).|:count cases ignorées (férié fermé).', $result['holidays']);
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
        $this->authorize('schedule_management.publish');

        DB::transaction(function (): void {
            $this->lockActiveEmployees();

            if (! $this->refuseIfWeekChanged()) {
                $this->publishCurrentWeek();
            }
        });
    }

    private function publishCurrentWeek(): void
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        $uncoveredCount = AppointmentBooking::uncoveredAppointments($start->toDateString(), $start->copy()->addDays(6)->toDateString())
            ->filter(fn (array $row): bool => $row['coverage']->blocksPublication())
            ->count();

        if ($uncoveredCount > 0) {
            Flux::toast(
                text: trans_choice(':count rendez-vous n\'est couvert par aucun quart. Ajoutez un quart ou réassignez-le avant de publier.|:count rendez-vous ne sont couverts par aucun quart. Ajoutez un quart ou réassignez-les avant de publier.', $uncoveredCount),
                heading: __('Publication refusée'),
                variant: 'danger',
            );

            return;
        }

        $this->activeWeekSchedules()
            ->where('status', ScheduleStatus::Draft->value)
            ->update(['status' => ScheduleStatus::Published, 'last_updated_by' => auth()->user()?->id]);

        $this->syncWeekSnapshot();
    }

    public function unpublishWeek(): void
    {
        $this->authorize('schedule_management.publish');

        DB::transaction(function (): void {
            $this->lockActiveEmployees();

            if (! $this->refuseIfWeekChanged()) {
                $this->unpublishCurrentWeek();
            }
        });
    }

    private function unpublishCurrentWeek(): void
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

        $this->syncWeekSnapshot();
    }

    public function deleteSchedule(): void
    {
        $this->authorize('schedule_management.edit');

        $weekWasCurrent = $this->isWeekSnapshotCurrent();

        $error = DB::transaction(function (): ?string {
            User::query()->whereKey($this->editingUserId)->lockForUpdate()->first();

            if (PayrollPeriod::isDateLocked($this->editingDate)) {
                return $this->lockedPeriodMessage();
            }

            $schedule = Schedule::query()
                ->where('user_id', $this->editingUserId)
                ->whereDate('date', $this->editingDate)
                ->first();

            $staleError = $this->staleShiftError($schedule);

            if ($staleError !== null) {
                return $staleError;
            }

            if (! $schedule) {
                return null;
            }

            if (! in_array($schedule->status, ScheduleStatus::editableValues())) {
                return __('Ce quart ne peut pas être supprimé (statut : :status).', [
                    'status' => $schedule->status->label(),
                ]);
            }

            $conflicts = Appointment::countOutsideWindow((int) $this->editingUserId, $this->editingDate, null);

            if ($conflicts > 0) {
                return $this->conflictMessage($conflicts);
            }

            $schedule->delete();

            return null;
        });

        if ($error !== null) {
            $this->addError('editingDate', $error);

            return;
        }

        $this->closeModal();
        $this->syncAfterOwnWrite($weekWasCurrent);
    }

    public function save(): void
    {
        $this->authorize('schedule_management.edit');

        if (PayrollPeriod::isDateLocked($this->editingDate)) {
            $this->addError('editingDate', __('Cette journée fait partie d\'une période de paie verrouillée.'));

            return;
        }

        $current = $this->editingScheduleId ? Schedule::query()->find($this->editingScheduleId) : null;

        if ($current && ! in_array($current->status, ScheduleStatus::editableValues())) {
            $this->addError('editingDate', __('Ce quart ne peut pas être modifié (statut : :status).', [
                'status' => $current->status->label(),
            ]));

            return;
        }

        $type = ScheduleType::tryFrom($this->scheduleType) ?? ScheduleType::Work;
        $isAbsence = $type->isAbsence();

        $this->validate([
            'scheduleType' => ['required', Rule::enum(ScheduleType::class)],
            'startTime' => $isAbsence ? [] : ['nullable', 'date_format:H:i', 'required_with:endTime'],
            'endTime' => $isAbsence ? [] : ['nullable', 'date_format:H:i', 'after:startTime', 'required_with:startTime'],
            'breakMinutes' => $isAbsence ? [] : ['required', 'integer', 'in:0,30,60'],
            'untilDate' => $isAbsence
                ? ['nullable', 'date_format:Y-m-d', 'after_or_equal:editingDate', 'before_or_equal:'.Carbon::parse($this->editingDate)->addYear()->toDateString()]
                : [],
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
            'type' => $type->value,
            'start_time' => ! $isAbsence && filled($this->startTime) ? $this->startTime : null,
            'end_time' => ! $isAbsence && filled($this->endTime) ? $this->endTime : null,
            'break_minutes' => $isAbsence ? 0 : $this->breakMinutes,
            'status' => $this->status,
            'notes' => filled($this->notes) ? $this->notes : null,
        ];

        $isRange = $isAbsence && filled($this->untilDate) && $this->untilDate > $this->editingDate;
        $weekWasCurrent = $this->isWeekSnapshotCurrent();

        $error = $isRange ? $this->writeAbsenceRange($data, $employee) : $this->writeSchedule($data);

        if ($error !== null) {
            $this->addError('editingDate', $error);

            return;
        }

        $this->closeModal();
        $this->syncAfterOwnWrite($weekWasCurrent);
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

            // La période de paie a pu être verrouillée depuis la vérification faite avant la transaction.
            if (PayrollPeriod::isDateLocked($this->editingDate)) {
                return $this->lockedPeriodMessage();
            }

            $existing = Schedule::query()
                ->where('user_id', $this->editingUserId)
                ->whereDate('date', $this->editingDate)
                ->first();

            $staleError = $this->staleShiftError($existing);

            if ($staleError !== null) {
                return $staleError;
            }

            if ($existing && ! in_array($existing->status, ScheduleStatus::editableValues())) {
                return __('Ce quart ne peut pas être modifié (statut : :status).', [
                    'status' => $existing->status->label(),
                ]);
            }

            // Un quart en brouillon peut couvrir des rendez-vous à venir, mais un quart publié qui en contient ne se dépublie pas.
            $isUnpublishing = $existing && $existing->status !== ScheduleStatus::Draft && $data['status'] === ScheduleStatus::Draft->value;

            $window = $isUnpublishing || $data['type'] !== ScheduleType::Work->value || ! $data['start_time'] || ! $data['end_time']
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

    /**
     * Enregistre une absence sur plusieurs jours : le premier jour suit toutes les règles d'un quart,
     * les suivants remplacent un quart modifiable et sont ignorés s'ils ne peuvent pas l'être.
     *
     * @param  array<string, mixed>  $data
     * @return string|null Message d'erreur, ou null si l'absence a été enregistrée.
     */
    private function writeAbsenceRange(array $data, User $employee): ?string
    {
        return DB::transaction(function () use ($data, $employee): ?string {
            $error = $this->writeSchedule($data);

            if ($error !== null) {
                return $error;
            }

            $saved = 1;
            $skipped = 0;
            $last = Carbon::parse($this->untilDate);

            for ($day = Carbon::parse($this->editingDate)->addDay(); $day->lte($last); $day->addDay()) {
                $this->fillAbsenceDay($employee, $day->toDateString(), $data) ? $saved++ : $skipped++;
            }

            $text = trans_choice(':count jour enregistré.|:count jours enregistrés.', $saved);

            if ($skipped > 0) {
                $text .= ' '.trans_choice(':count jour ignoré (quart fermé ou payé, période de paie verrouillée, hors période de travail, ou rendez-vous déjà pris).|:count jours ignorés (quart fermé ou payé, période de paie verrouillée, hors période de travail, ou rendez-vous déjà pris).', $skipped);
            }

            Flux::toast(text: $text, heading: __('Absence enregistrée'), variant: $skipped > 0 ? 'warning' : 'success');

            return null;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillAbsenceDay(User $employee, string $date, array $data): bool
    {
        $inService = $employee->first_day
            && $date >= $employee->first_day->toDateString()
            && (! $employee->last_day || $date <= $employee->last_day->toDateString());

        $existing = Schedule::query()->where('user_id', $employee->id)->whereDate('date', $date)->first();

        if (! $inService
            || PayrollPeriod::isDateLocked($date)
            || ($existing && ! in_array($existing->status, ScheduleStatus::editableValues()))
            || Appointment::countOutsideWindow($employee->id, $date, null) > 0) {
            return false;
        }

        if ($existing) {
            $existing->update($data);
        } else {
            Schedule::create(['user_id' => $employee->id, 'date' => $date, ...$data]);
        }

        return true;
    }

    /** Ouvre la réassignation d'un rendez-vous à venir (autre employé, autre jour ou autre heure). */
    public function openReassign(int $appointmentId): void
    {
        $this->authorize('appointments.edit');

        $appointment = Appointment::query()->find($appointmentId);

        if (! $appointment) {
            Flux::toast(text: __('Ce rendez-vous a été supprimé par un autre usager.'), heading: __('Réassignation impossible'), variant: 'danger');

            return;
        }

        $this->closeModal();

        $this->reassigningAppointmentId = $appointment->id;
        $this->reassignVersion = $appointment->updated_at?->getTimestamp() ?? 0;
        $this->reassignUserId = $appointment->user_id;
        $this->reassignDate = $appointment->date;
        $this->reassignStartMinute = $appointment->start_minute;
        $this->resetValidation();

        $this->showReassignModal = true;
    }

    public function reassign(): void
    {
        $this->authorize('appointments.edit');

        $this->validate([
            'reassignUserId' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'reassignDate' => ['required', 'date_format:Y-m-d'],
            'reassignStartMinute' => ['required', 'integer', 'min:'.AppointmentBooking::DAY_START_MINUTE, 'max:'.(AppointmentBooking::DAY_END_MINUTE - AppointmentBooking::SLOT_MINUTES)],
        ]);

        $appointment = Appointment::query()->find($this->reassigningAppointmentId);

        if (! $appointment) {
            $this->addError('reassignUserId', __('Ce rendez-vous a été supprimé par un autre usager.'));

            return;
        }

        try {
            $appointment = app(ReassignAppointment::class)->handle($appointment, (int) $this->reassignUserId, $this->reassignDate, $this->reassignStartMinute, $this->reassignVersion);
        } catch (DomainException $exception) {
            $this->addError('reassignUserId', $exception->getMessage());

            return;
        }

        Flux::toast(
            text: __('Rendez-vous réassigné à :name, le :date à :time.', [
                'name' => $appointment->user?->fullName(),
                'date' => Carbon::parse($appointment->date)->translatedFormat('l d F'),
                'time' => AppointmentBooking::formatMinute($appointment->start_minute),
            ]),
            heading: __('Rendez-vous réassigné'),
            variant: 'success',
        );

        $this->closeReassignModal();
    }

    public function closeReassignModal(): void
    {
        $this->showReassignModal = false;
        $this->resetValidation();
        $this->reset(['reassigningAppointmentId', 'reassignVersion', 'reassignUserId', 'reassignDate']);
        $this->reassignStartMinute = AppointmentBooking::DAY_START_MINUTE;
    }

    /**
     * Refus si le quart de la case a été créé, modifié ou supprimé par quelqu'un d'autre depuis l'ouverture de la fenêtre.
     */
    private function staleShiftError(?Schedule $existing): ?string
    {
        if ($this->editingScheduleId !== null && ! $existing) {
            return __('Ce quart a été supprimé par un autre usager.');
        }

        if (! $existing || ($existing->id === $this->editingScheduleId && $existing->updated_at?->getTimestamp() === $this->editingVersion)) {
            return null;
        }

        $editor = $existing->lastUpdatedBy?->fullName();
        $time = $existing->updated_at?->format('H:i');

        return $editor
            ? __('Ce quart a été modifié entre-temps par :name à :time. Fermez et rouvrez la fenêtre pour voir les changements.', ['name' => $editor, 'time' => $time])
            : __('Ce quart a été modifié entre-temps à :time. Fermez et rouvrez la fenêtre pour voir les changements.', ['time' => $time]);
    }

    private function lockedPeriodMessage(): string
    {
        return __('Cette journée fait partie d\'une période de paie verrouillée.');
    }

    /**
     * Nombre de quarts de la semaine affichée et horodatage de la dernière modification.
     *
     * @return array{count: int, latest: int}
     */
    private function weekSnapshot(): array
    {
        $latest = $this->activeWeekSchedules()->max('updated_at');

        return [
            'count' => $this->activeWeekSchedules()->count(),
            'latest' => $latest ? Carbon::parse($latest)->getTimestamp() : 0,
        ];
    }

    private function syncWeekSnapshot(): void
    {
        ['count' => $this->weekShiftCount, 'latest' => $this->weekLatestUpdate] = $this->weekSnapshot();
    }

    private function isWeekSnapshotCurrent(): bool
    {
        return $this->weekSnapshot() === ['count' => $this->weekShiftCount, 'latest' => $this->weekLatestUpdate];
    }

    /**
     * Après une écriture de l'usager : la grille redevient la référence, sauf si la semaine avait déjà été modifiée
     * par quelqu'un d'autre (l'avis reste alors affiché).
     */
    private function syncAfterOwnWrite(bool $weekWasCurrent): void
    {
        if ($weekWasCurrent) {
            $this->syncWeekSnapshot();
        }
    }

    /** Avis à afficher si la semaine a été modifiée par quelqu'un d'autre depuis la dernière synchronisation. */
    private function weekChangeNotice(): ?string
    {
        if ($this->isWeekSnapshotCurrent()) {
            return null;
        }

        $latest = $this->activeWeekSchedules()
            ->where('updated_at', '>', Carbon::createFromTimestamp($this->weekLatestUpdate, config('app.timezone')))
            ->with('lastUpdatedBy')
            ->latest('updated_at')
            ->first();

        if (! $latest) {
            return __('Un quart de cette semaine a été supprimé par un autre usager.');
        }

        $editor = $latest->lastUpdatedBy?->fullName();
        $time = $latest->updated_at?->format('H:i');

        return $editor
            ? __('L\'horaire de cette semaine a été modifié par :name à :time.', ['name' => $editor, 'time' => $time])
            : __('L\'horaire de cette semaine a été modifié à :time.', ['time' => $time]);
    }

    /**
     * Refuse une action sur toute la semaine si quelqu'un d'autre l'a modifiée depuis son affichage, puis rafraîchit la grille.
     */
    private function refuseIfWeekChanged(): bool
    {
        $notice = $this->weekChangeNotice();

        if ($notice === null) {
            return false;
        }

        Flux::toast(
            text: $notice.' '.__('La grille a été rafraîchie : vérifiez-la, puis recommencez.'),
            heading: __('Action refusée'),
            variant: 'danger',
        );

        $this->syncWeekSnapshot();

        return true;
    }

    /** Sérialise les actions sur toute la semaine avec les écritures quart par quart (verrou par employé). */
    private function lockActiveEmployees(): void
    {
        User::query()->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
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
        $this->reset(['editingUserId', 'editingScheduleId', 'editingVersion', 'editingDate', 'startTime', 'endTime', 'breakMinutes', 'status', 'notes', 'shiftTemplateId', 'untilDate']);
        $this->scheduleType = ScheduleType::Work->value;
    }

    /**
     * Données de la fenêtre de réassignation : le rendez-vous, et pour chaque employé actif son quart à la date choisie.
     *
     * @return array{reassigningAppointment: Appointment|null, reassignCandidates: array<int, array{id: int, label: string}>, reassignTimeSlots: array<int, string>}
     */
    private function reassignViewData(): array
    {
        $appointment = $this->showReassignModal && $this->reassigningAppointmentId
            ? Appointment::query()->with(['user', 'customer'])->find($this->reassigningAppointmentId)
            : null;

        if (! $appointment || ! Carbon::hasFormat($this->reassignDate, 'Y-m-d')) {
            return ['reassigningAppointment' => $appointment, 'reassignCandidates' => [], 'reassignTimeSlots' => []];
        }

        $shifts = Schedule::query()
            ->whereDate('date', $this->reassignDate)
            ->get()
            ->keyBy('user_id');

        $candidates = User::query()->where('is_active', true)->orderBy('id')->get()
            ->map(function (User $user) use ($shifts): array {
                $shift = $shifts->get($user->id);

                $detail = match (true) {
                    ! $shift => __('aucun quart'),
                    $shift->type->isAbsence() => $shift->type->label(),
                    ! $shift->start_time || ! $shift->end_time => $shift->type->label(),
                    default => substr($shift->start_time, 0, 5).'–'.substr($shift->end_time, 0, 5)
                        .($shift->status === ScheduleStatus::Draft ? ' ('.__('non publié').')' : ''),
                };

                return ['id' => $user->id, 'label' => $user->fullName().' — '.$detail];
            })
            ->values()
            ->all();

        $timeSlots = [];
        for ($minute = AppointmentBooking::DAY_START_MINUTE; $minute < AppointmentBooking::DAY_END_MINUTE; $minute += AppointmentBooking::SLOT_MINUTES) {
            $timeSlots[$minute] = AppointmentBooking::formatMinute($minute);
        }

        return ['reassigningAppointment' => $appointment, 'reassignCandidates' => $candidates, 'reassignTimeSlots' => $timeSlots];
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

        $holidays = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()])
            ->get()
            ->keyBy('date');

        $weekAppointments = Appointment::query()
            ->whereIn('user_id', $userIds)
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $start->copy()->addDays(7)->toDateString())
            ->with('customer')
            ->orderBy('start_minute')
            ->get()
            ->groupBy(fn (Appointment $appointment): string => $appointment->user_id.'|'.$appointment->date);

        $uncoveredAppointments = AppointmentBooking::uncoveredAppointments($start->toDateString(), $start->copy()->addDays(6)->toDateString());

        return view('livewire.schedules.schedule-edit', [
            'holidays' => $holidays,
            'scheduleTypes' => ScheduleType::cases(),
            'shiftTemplates' => ShiftTemplate::query()->orderBy('name')->get(),
            'weekTemplates' => WeekTemplate::query()->orderBy('name')->get(),
            'editingSchedule' => $editingSchedule,
            'users' => $users,
            'days' => $days,
            'schedules' => $schedules,
            'totalHours' => $totalHours,
            'startDate' => $start,
            'endDate' => $start->copy()->addDays(6),
            'isCurrentWeek' => $start->isSameDay(Carbon::now()->startOfWeek(Carbon::SUNDAY)),
            'weekAppointments' => $weekAppointments,
            'weekChangeNotice' => $this->weekChangeNotice(),
            'uncoveredAppointments' => $uncoveredAppointments,
            'uncoveredCoverage' => $uncoveredAppointments->mapWithKeys(fn (array $row): array => [$row['appointment']->id => $row['coverage']]),
            'editingAppointments' => $this->showModal && $this->editingUserId
                ? $weekAppointments->get($this->editingUserId.'|'.$this->editingDate, collect())
                : collect(),
            ...$this->reassignViewData(),
        ])->layout('layouts.app', ['title' => __('Gestion des horaires')]);
    }
}
