<?php

namespace App\Livewire\Schedules;

use App\Models\Appointment;
use App\Models\customer;
use App\Models\Schedule;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Appointments extends Component
{
    /** Taille d'une case de la grille, en minutes (15 ou 30). */
    public const SLOT_MINUTES = 30;

    public const DAY_START_MINUTE = 8 * 60;

    public const DAY_END_MINUTE = 19 * 60;

    public string $weekStart;

    public ?int $selectedUserId = null;

    public bool $showModal = false;

    public string $editingDate = '';

    public int $editingStartMinute = self::DAY_START_MINUTE;

    public string $title = '';

    public string $notes = '';

    public ?int $editingAppointmentId = null;

    /** Horodatage updated_at du rendez-vous à l'ouverture, pour détecter une modification concurrente. */
    public int $editingVersion = 0;

    public int $durationMinutes = self::SLOT_MINUTES;

    public ?int $selectedCustomerId = null;

    public string $customerSearch = '';

    public function mount(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

        $firstUser = User::query()->where('is_active', true)->orderBy('id')->first();
        $this->selectedUserId = $firstUser?->id;
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

    public function openCell(string $date, int $startMinute): void
    {
        if (! $this->canBookAt($date, $startMinute)) {
            return;
        }

        $this->editingDate = $date;
        $this->editingStartMinute = $startMinute;

        $existing = Appointment::query()
            ->where('user_id', $this->selectedUserId)
            ->where('date', $date)
            ->where('start_minute', '>=', $startMinute)
            ->where('start_minute', '<', $startMinute + self::SLOT_MINUTES)
            ->with('customer')
            ->first();

        $this->editingAppointmentId = $existing?->id;
        $this->editingVersion = $existing?->updated_at?->getTimestamp() ?? 0;
        $this->title = $existing ? $existing->title : '';
        $this->notes = $existing ? ($existing->notes ?? '') : '';
        $this->durationMinutes = $existing ? $existing->duration_minutes : self::SLOT_MINUTES;
        $this->selectedCustomerId = $existing?->customer_id;
        $this->customerSearch = $existing?->customer
            ? $existing->customer->firstname.' '.$existing->customer->lastname
            : '';

        $this->showModal = true;
    }

    public function selectCustomer(int $id): void
    {
        $c = customer::findOrFail($id);
        $this->selectedCustomerId = $id;
        $this->customerSearch = $c->firstname.' '.$c->lastname;
    }

    public function clearCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->customerSearch = '';
    }

    #[On('customer-saved')]
    public function onCustomerSaved(int $id): void
    {
        $this->selectCustomer($id);
    }

    public function save(): void
    {
        if (! $this->editingDate || ! $this->canBookAt($this->editingDate, $this->editingStartMinute)) {
            $this->addError('title', __('Ce créneau n\'est pas disponible.'));

            return;
        }

        $maxDuration = $this->maxDurationMinutes($this->editingDate, $this->editingStartMinute, $this->editingAppointmentId);

        $this->validate([
            'selectedUserId' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'selectedCustomerId' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'durationMinutes' => ['required', 'integer', 'min:'.self::SLOT_MINUTES, 'max:'.$maxDuration, 'multiple_of:'.self::SLOT_MINUTES],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'durationMinutes.max' => __('La durée chevauche un autre rendez-vous ou dépasse l\'horaire de l\'employé.'),
        ]);

        $data = [
            'user_id' => $this->selectedUserId,
            'customer_id' => $this->selectedCustomerId,
            'date' => $this->editingDate,
            'start_minute' => $this->editingStartMinute,
            'duration_minutes' => $this->durationMinutes,
            'title' => $this->title,
            'notes' => filled($this->notes) ? $this->notes : null,
        ];

        $failure = $this->writeIfAvailable(
            $this->editingDate,
            $this->editingStartMinute,
            $this->durationMinutes,
            $this->editingAppointmentId,
            $this->editingVersion,
            fn () => $this->editingAppointmentId
                ? $this->employeeAppointments()->findOrFail($this->editingAppointmentId)->update($data)
                : Appointment::create($data),
        );

        if ($failure === 'gone') {
            $this->rejectMove($this->goneMessage(), __('Enregistrement refusé'));
            $this->closeModal();

            return;
        }

        if ($failure === 'stale') {
            $this->addError('title', $this->staleMessage($this->editingAppointmentId));

            return;
        }

        if ($failure === 'taken') {
            $this->addError('durationMinutes', __('Cette plage vient d\'être prise par un autre rendez-vous. Choisissez un autre créneau.'));

            return;
        }

        $this->closeModal();
    }

    /**
     * Déplace et/ou redimensionne un rendez-vous (glisser-déposer). Un changement invalide est ignoré.
     */
    public function updateAppointmentTime(int $id, string $date, int $startMinute, int $durationMinutes, int $version): void
    {
        $appointment = $this->employeeAppointments()->find($id);

        if (! $appointment) {
            $this->rejectMove($this->goneMessage());

            return;
        }

        $originalStart = Carbon::parse($appointment->date)->startOfDay()->addMinutes($appointment->start_minute);
        $isAligned = $startMinute % self::SLOT_MINUTES === 0 && $durationMinutes % self::SLOT_MINUTES === 0;

        if (! Carbon::hasFormat($date, 'Y-m-d') || $originalStart->lt(Carbon::now()) || ! $isAligned || $durationMinutes < self::SLOT_MINUTES) {
            $this->rejectMove(__('Ce déplacement n\'est pas possible.'));

            return;
        }

        if (! $this->canBookAt($date, $startMinute)) {
            $this->rejectMove(__('Ce créneau n\'est pas disponible : passé ou hors de l\'horaire de l\'employé.'));

            return;
        }

        $failure = $this->writeIfAvailable($date, $startMinute, $durationMinutes, $appointment->id, $version, fn () => $appointment->update([
            'date' => $date,
            'start_minute' => $startMinute,
            'duration_minutes' => $durationMinutes,
        ]));

        if ($failure === 'gone') {
            $this->rejectMove($this->goneMessage());
        } elseif ($failure === 'stale') {
            $this->rejectMove($this->staleMessage($appointment->id));
        } elseif ($failure === 'taken') {
            $this->rejectMove(__('Ce rendez-vous chevauche un autre rendez-vous ou dépasse l\'horaire de l\'employé.'));
        }
    }

    /**
     * Exécute l'écriture sous verrou : les réservations d'un même employé sont sérialisées,
     * puis la disponibilité et la version du rendez-vous sont revérifiées avant d'écrire.
     *
     * @param  callable(): mixed  $write
     * @return 'taken'|'stale'|'gone'|null Motif du refus, ou null si l'écriture a eu lieu.
     */
    private function writeIfAvailable(string $date, int $startMinute, int $durationMinutes, ?int $appointmentId, int $version, callable $write): ?string
    {
        return DB::transaction(function () use ($date, $startMinute, $durationMinutes, $appointmentId, $version, $write): ?string {
            User::query()->whereKey($this->selectedUserId)->lockForUpdate()->first();

            if ($appointmentId !== null) {
                $current = $this->employeeAppointments()->find($appointmentId);

                if (! $current) {
                    return 'gone';
                }

                if ($current->updated_at?->getTimestamp() !== $version) {
                    return 'stale';
                }
            }

            if ($durationMinutes > $this->maxDurationMinutes($date, $startMinute, $appointmentId)) {
                return 'taken';
            }

            $write();

            return null;
        });
    }

    private function goneMessage(): string
    {
        return __('Ce rendez-vous a été supprimé par un autre usager.');
    }

    private function staleMessage(?int $appointmentId): string
    {
        $editor = Appointment::query()->with('lastUpdatedBy')->find($appointmentId)?->lastUpdatedBy?->fullName();

        return $editor
            ? __('Ce rendez-vous a été modifié entre-temps par :name. Fermez et rouvrez-le pour voir les changements.', ['name' => $editor])
            : __('Ce rendez-vous a été modifié entre-temps. Fermez et rouvrez-le pour voir les changements.');
    }

    private function rejectMove(string $message, ?string $heading = null): void
    {
        Flux::toast(text: $message, heading: $heading ?? __('Déplacement refusé'), variant: 'danger');
    }

    public function deleteAppointment(): void
    {
        if ($this->editingAppointmentId) {
            $this->employeeAppointments()->whereKey($this->editingAppointmentId)->delete();
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['editingDate', 'title', 'notes', 'editingAppointmentId', 'editingVersion', 'selectedCustomerId', 'customerSearch']);
        $this->resetValidation();
        $this->editingStartMinute = self::DAY_START_MINUTE;
        $this->durationMinutes = self::SLOT_MINUTES;
    }

    /**
     * @return Builder<Appointment>
     */
    private function employeeAppointments(): Builder
    {
        return Appointment::query()->where('user_id', $this->selectedUserId);
    }

    /**
     * Une case est réservable si elle est dans la grille, dans le futur et, pour la semaine
     * en cours, à l'intérieur de l'horaire de l'employé.
     */
    private function canBookAt(string $date, int $startMinute): bool
    {
        if ($startMinute < self::DAY_START_MINUTE || $startMinute >= self::DAY_END_MINUTE) {
            return false;
        }

        if (Carbon::parse($date)->startOfDay()->addMinutes($startMinute)->lt(Carbon::now())) {
            return false;
        }

        if ($this->isInCurrentWeek($date) && ! $this->employeeWorksAt($date, $startMinute)) {
            return false;
        }

        return true;
    }

    /**
     * Plage de travail de l'employé sélectionné pour une date, en minutes depuis minuit.
     *
     * @return array{0: int, 1: int}|null
     */
    private function workingWindow(string $date): ?array
    {
        $schedule = Schedule::query()
            ->where('user_id', $this->selectedUserId)
            ->where('date', $date)
            ->first();

        if (! $schedule || ! $schedule->start_time || ! $schedule->end_time) {
            return null;
        }

        return [
            $this->minutesOf($schedule->start_time),
            $this->minutesOf($schedule->end_time),
        ];
    }

    private function employeeWorksAt(string $date, int $startMinute): bool
    {
        $window = $this->workingWindow($date);

        return $window !== null && $startMinute >= $window[0] && $startMinute < $window[1];
    }

    private function isInCurrentWeek(string $date): bool
    {
        return Carbon::parse($date)->startOfWeek(Carbon::SUNDAY)->isSameDay(Carbon::now()->startOfWeek(Carbon::SUNDAY));
    }

    private function minutesOf(string $time): int
    {
        $parsed = Carbon::parse($time);

        return $parsed->hour * 60 + $parsed->minute;
    }

    /**
     * Durée maximale réservable depuis $startMinute : limitée par la fin de journée,
     * la fin de l'horaire (semaine en cours) et le prochain rendez-vous de l'employé.
     */
    private function maxDurationMinutes(string $date, int $startMinute, ?int $ignoreAppointmentId = null): int
    {
        $limit = self::DAY_END_MINUTE;

        if ($this->isInCurrentWeek($date)) {
            $window = $this->workingWindow($date);
            $limit = min($limit, $window[1] ?? $startMinute);
        }

        $others = $this->employeeAppointments()
            ->where('date', $date)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId));

        $isStartTaken = (clone $others)
            ->where('start_minute', '<=', $startMinute)
            ->whereRaw('start_minute + duration_minutes > ?', [$startMinute])
            ->exists();

        if ($isStartTaken) {
            return 0;
        }

        $nextStart = (clone $others)->where('start_minute', '>', $startMinute)->min('start_minute');

        if ($nextStart !== null) {
            $limit = min($limit, (int) $nextStart);
        }

        return max(0, $limit - $startMinute);
    }

    private function snapToSlot(int $minute): int
    {
        return self::DAY_START_MINUTE + intdiv($minute - self::DAY_START_MINUTE, self::SLOT_MINUTES) * self::SLOT_MINUTES;
    }

    public function render(): View
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        $days = Collection::times(7, fn ($i) => $start->copy()->addDays($i - 1));

        $timeSlots = range(self::DAY_START_MINUTE, self::DAY_END_MINUTE - self::SLOT_MINUTES, self::SLOT_MINUTES);

        $users = User::query()->where('is_active', true)->orderBy('id')->get();

        $appointments = $this->selectedUserId
            ? Appointment::query()
                ->where('user_id', $this->selectedUserId)
                ->where('date', '>=', $start->toDateString())
                ->where('date', '<', $start->copy()->addDays(7)->toDateString())
                ->with('customer')
                ->get()
                ->groupBy('date')
                ->map(fn ($rows) => $rows->keyBy(fn ($a) => $this->snapToSlot($a->start_minute)))
            : collect();

        $schedules = $this->selectedUserId
            ? Schedule::query()
                ->where('user_id', $this->selectedUserId)
                ->where('date', '>=', $start->toDateString())
                ->where('date', '<', $start->copy()->addDays(7)->toDateString())
                ->get()
                ->keyBy(fn ($s) => Carbon::parse($s->date)->toDateString())
            : collect();

        $customerResults = strlen($this->customerSearch) >= 4 && ! $this->selectedCustomerId
            ? customer::query()
                ->where(function ($q) {
                    $q->where('firstname', 'like', '%'.$this->customerSearch.'%')
                        ->orWhere('lastname', 'like', '%'.$this->customerSearch.'%')
                        ->orWhere('phone', 'like', '%'.$this->customerSearch.'%')
                        ->orWhere('cellphone', 'like', '%'.$this->customerSearch.'%');
                })
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->limit(8)
                ->get()
            : collect();

        $isCurrentWeek = $start->isSameDay(Carbon::now()->startOfWeek(Carbon::SUNDAY));
        $isPastWeek = $start->lt(Carbon::now()->startOfWeek(Carbon::SUNDAY));

        // Cases couvertes par un rendez-vous de plusieurs créneaux (clés date:minute à sauter).
        $coveredCells = collect();
        foreach ($appointments as $dateKey => $bySlot) {
            foreach ($bySlot as $slot => $appt) {
                for ($m = $slot + self::SLOT_MINUTES; $m < $appt->endMinute(); $m += self::SLOT_MINUTES) {
                    $coveredCells->push($dateKey.':'.$m);
                }
            }
        }

        // Créneaux réservables par jour (même règle que canBookAt, sans requête supplémentaire).
        $now = Carbon::now();
        $openSlots = [];
        foreach ($days as $day) {
            $dateKey = $day->toDateString();
            $schedule = $schedules->get($dateKey);
            $window = $schedule && $schedule->start_time && $schedule->end_time
                ? [$this->minutesOf($schedule->start_time), $this->minutesOf($schedule->end_time)]
                : null;

            foreach ($timeSlots as $slot) {
                $inFuture = $day->copy()->startOfDay()->addMinutes($slot)->gte($now);
                $inWindow = ! $isCurrentWeek || ($window !== null && $slot >= $window[0] && $slot < $window[1]);
                $openSlots[$dateKey][$slot] = $inFuture && $inWindow;
            }
        }

        $gridData = [
            'open' => collect($openSlots)->map(fn ($slots) => array_keys(array_filter($slots)))->all(),
            'appointments' => $appointments->flatten()->map(fn ($a) => [
                'id' => $a->id,
                'date' => $a->date,
                'start' => $a->start_minute,
                'end' => $a->endMinute(),
            ])->values()->all(),
        ];

        $maxDuration = $this->showModal
            ? $this->maxDurationMinutes($this->editingDate, $this->editingStartMinute, $this->editingAppointmentId)
            : self::SLOT_MINUTES;

        $editingAppointment = $this->showModal && $this->editingAppointmentId
            ? Appointment::query()->with(['creator', 'lastUpdatedBy'])->find($this->editingAppointmentId)
            : null;

        return view('livewire.schedules.appointments', [
            'editingAppointment' => $editingAppointment,
            'users' => $users,
            'days' => $days,
            'timeSlots' => $timeSlots,
            'openSlots' => $openSlots,
            'gridData' => $gridData,
            'slotMinutes' => self::SLOT_MINUTES,
            'appointments' => $appointments,
            'schedules' => $schedules,
            'customerResults' => $customerResults,
            'coveredCells' => $coveredCells,
            'maxDuration' => $maxDuration,
            'startDate' => $start,
            'endDate' => $start->copy()->addDays(6),
            'isCurrentWeek' => $isCurrentWeek,
            'isPastWeek' => $isPastWeek,
        ])->layout('layouts.app', ['title' => __('Rendez-vous')]);
    }
}
