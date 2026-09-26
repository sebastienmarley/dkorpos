<?php

namespace App\Livewire\Schedules;

use App\Models\Appointment;
use App\Models\customer;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class Appointments extends Component
{
    public string $weekStart;

    public ?int $selectedUserId = null;

    public bool $showModal = false;

    public string $editingDate = '';

    public int $editingHour = 8;

    public string $title = '';

    public string $notes = '';

    public ?int $editingAppointmentId = null;

    public int $durationHours = 1;

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

    public function openCell(string $date, int $hour): void
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);
        $isPast = Carbon::parse($date)->startOfDay()->lt(Carbon::now()->startOfDay());

        if ($isPast) {
            return;
        }

        $isCurrentWeek = $start->isSameWeek(Carbon::now());

        if ($isCurrentWeek && ! $this->employeeWorksAt($date, $hour)) {
            return;
        }

        $this->editingDate = $date;
        $this->editingHour = $hour;

        $existing = Appointment::query()
            ->where('user_id', $this->selectedUserId)
            ->where('date', $date)
            ->where('hour', $hour)
            ->with('customer')
            ->first();

        $this->editingAppointmentId = $existing?->id;
        $this->title = $existing?->title ?? '';
        $this->notes = $existing?->notes ?? '';
        $this->durationHours = $existing?->duration_hours ?? 1;
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
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'durationHours' => ['required', 'integer', 'min:1', 'max:11'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'user_id' => $this->selectedUserId,
            'customer_id' => $this->selectedCustomerId,
            'date' => $this->editingDate,
            'hour' => $this->editingHour,
            'duration_hours' => $this->durationHours,
            'title' => $this->title,
            'notes' => filled($this->notes) ? $this->notes : null,
        ];

        if ($this->editingAppointmentId) {
            Appointment::findOrFail($this->editingAppointmentId)->update($data);
        } else {
            Appointment::create($data);
        }

        $this->closeModal();
    }

    public function deleteAppointment(): void
    {
        if ($this->editingAppointmentId) {
            Appointment::findOrFail($this->editingAppointmentId)->delete();
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['editingDate', 'editingHour', 'title', 'notes', 'editingAppointmentId', 'selectedCustomerId', 'customerSearch', 'durationHours']);
        $this->editingHour = 8;
        $this->durationHours = 1;
    }

    /**
     * Check if the selected employee has a schedule that covers the given hour on a date.
     */
    private function employeeWorksAt(string $date, int $hour): bool
    {
        $schedule = Schedule::query()
            ->where('user_id', $this->selectedUserId)
            ->where('date', $date)
            ->first();

        if (! $schedule || ! $schedule->start_time || ! $schedule->end_time) {
            return false;
        }

        $scheduleStartHour = (int) Carbon::parse($schedule->start_time)->format('H');
        $scheduleEndHour = (int) Carbon::parse($schedule->end_time)->format('H');

        return $hour >= $scheduleStartHour && $hour < $scheduleEndHour;
    }

    public function render(): View
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek(Carbon::SUNDAY);

        $days = Collection::times(7, fn ($i) => $start->copy()->addDays($i - 1));

        $hours = range(8, 18);

        $users = User::query()->where('is_active', true)->orderBy('id')->get();

        $appointments = $this->selectedUserId
            ? Appointment::query()
                ->where('user_id', $this->selectedUserId)
                ->where('date', '>=', $start->toDateString())
                ->where('date', '<', $start->copy()->addDays(7)->toDateString())
                ->with('customer')
                ->get()
                ->groupBy('date')
                ->map(fn ($rows) => $rows->keyBy('hour'))
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

        $isCurrentWeek = $start->isSameWeek(Carbon::now());
        $isPastWeek = $start->lt(Carbon::now()->startOfWeek(Carbon::SUNDAY));

        // Build set of cells covered by multi-hour appointments (date:hour keys to skip).
        $coveredCells = collect();
        foreach ($appointments as $dateKey => $byHour) {
            foreach ($byHour as $hour => $appt) {
                for ($i = 1; $i < $appt->duration_hours; $i++) {
                    $coveredCells->push($dateKey.':'.(string) ($hour + $i));
                }
            }
        }

        // Max duration selectable in the modal (hours 8–18 = 11 slots max).
        $maxDuration = $this->editingHour ? (18 - $this->editingHour + 1) : 11;

        return view('livewire.schedules.appointments', [
            'users' => $users,
            'days' => $days,
            'hours' => $hours,
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
