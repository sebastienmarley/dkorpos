<?php

namespace App\Livewire\Schedules;

use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WeekTemplate;
use App\Models\WeekTemplateEntry;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Templates extends Component
{
    public bool $showShiftModal = false;

    public ?int $editingShiftId = null;

    public string $shiftName = '';

    public string $shiftStart = '';

    public string $shiftEnd = '';

    public int $shiftBreak = 0;

    public bool $showWeekModal = false;

    public ?int $editingWeekId = null;

    public string $weekName = '';

    public string $selectedWeekTemplateId = '';

    /** @var array<int|string, array<int|string, string>> Quart type choisi par employé puis par jour (0 = dimanche). */
    public array $entries = [];

    // ── Quarts type ────────────────────────────────────────────────────────

    public function openCreateShift(): void
    {
        $this->resetValidation();
        $this->reset(['editingShiftId', 'shiftName', 'shiftStart', 'shiftEnd', 'shiftBreak']);
        $this->showShiftModal = true;
    }

    public function openEditShift(int $id): void
    {
        $shift = ShiftTemplate::query()->findOrFail($id);

        $this->resetValidation();
        $this->editingShiftId = $shift->id;
        $this->shiftName = $shift->name;
        $this->shiftStart = substr($shift->start_time, 0, 5);
        $this->shiftEnd = substr($shift->end_time, 0, 5);
        $this->shiftBreak = $shift->break_minutes;
        $this->showShiftModal = true;
    }

    public function saveShift(): void
    {
        $this->validate([
            'shiftName' => ['required', 'string', 'max:100', Rule::unique('shift_templates', 'name')->ignore($this->editingShiftId)],
            'shiftStart' => ['required', 'date_format:H:i'],
            'shiftEnd' => ['required', 'date_format:H:i', 'after:shiftStart'],
            'shiftBreak' => ['required', 'integer', 'in:0,30,60'],
        ]);

        $data = [
            'name' => $this->shiftName,
            'start_time' => $this->shiftStart,
            'end_time' => $this->shiftEnd,
            'break_minutes' => $this->shiftBreak,
        ];

        if ($this->editingShiftId) {
            ShiftTemplate::query()->findOrFail($this->editingShiftId)->update($data);
            Flux::toast(text: __('Quart type mis à jour.'), variant: 'success');
        } else {
            ShiftTemplate::create($data);
            Flux::toast(text: __('Quart type créé.'), variant: 'success');
        }

        $this->showShiftModal = false;
        $this->reset(['editingShiftId', 'shiftName', 'shiftStart', 'shiftEnd', 'shiftBreak']);
    }

    public function deleteShift(int $id): void
    {
        ShiftTemplate::query()->whereKey($id)->delete();

        $this->loadEntries();
        Flux::toast(text: __('Quart type supprimé.'), variant: 'success');
    }

    // ── Semaines type ──────────────────────────────────────────────────────

    public function openCreateWeek(): void
    {
        $this->resetValidation();
        $this->reset(['editingWeekId', 'weekName']);
        $this->showWeekModal = true;
    }

    public function openRenameWeek(): void
    {
        $week = $this->selectedWeekTemplate();

        if (! $week) {
            return;
        }

        $this->resetValidation();
        $this->editingWeekId = $week->id;
        $this->weekName = $week->name;
        $this->showWeekModal = true;
    }

    public function saveWeek(): void
    {
        $this->validate([
            'weekName' => ['required', 'string', 'max:100', Rule::unique('week_templates', 'name')->ignore($this->editingWeekId)],
        ]);

        if ($this->editingWeekId) {
            WeekTemplate::query()->findOrFail($this->editingWeekId)->update(['name' => $this->weekName]);
        } else {
            $week = WeekTemplate::create(['name' => $this->weekName]);
            $this->selectedWeekTemplateId = (string) $week->id;
            $this->loadEntries();
        }

        $this->showWeekModal = false;
        $this->reset(['editingWeekId', 'weekName']);
    }

    public function deleteWeek(): void
    {
        $this->selectedWeekTemplate()?->delete();

        $this->selectedWeekTemplateId = '';
        $this->loadEntries();
        Flux::toast(text: __('Semaine type supprimée.'), variant: 'success');
    }

    public function updatedSelectedWeekTemplateId(): void
    {
        $this->loadEntries();
    }

    /**
     * Enregistre aussitôt le quart type choisi pour un employé et un jour.
     *
     * Selon l'état côté navigateur, Livewire fournit la case modifiée (clé « employé.jour »),
     * le tableau d'un employé (clé « employé ») ou tout le tableau (sans clé).
     */
    public function updatedEntries(mixed $value, ?string $key = null): void
    {
        $segments = $key === null || $key === '' ? [] : explode('.', $key);

        if (count($segments) === 2) {
            $this->saveEntry((int) $segments[0], (int) $segments[1], (string) ($value ?? ''));

            return;
        }

        foreach ($this->entriesByCell($value, $segments) as [$userId, $weekday, $shiftId]) {
            $this->saveEntry($userId, $weekday, $shiftId);
        }
    }

    /**
     * @param  array<int, string>  $segments  Chemin déjà connu : [] (tout le tableau) ou [employé].
     * @return list<array{0: int, 1: int, 2: string}>
     */
    private function entriesByCell(mixed $value, array $segments): array
    {
        if (! is_array($value)) {
            return [];
        }

        $cells = [];

        if ($segments === []) {
            foreach ($value as $userId => $byWeekday) {
                foreach (is_array($byWeekday) ? $byWeekday : [] as $weekday => $shiftId) {
                    $cells[] = [(int) $userId, (int) $weekday, (string) ($shiftId ?? '')];
                }
            }

            return $cells;
        }

        foreach ($value as $weekday => $shiftId) {
            $cells[] = [(int) $segments[0], (int) $weekday, (string) ($shiftId ?? '')];
        }

        return $cells;
    }

    private function saveEntry(int $userId, int $weekday, string $value): void
    {
        $week = $this->selectedWeekTemplate();
        $employee = User::query()->where('is_active', true)->find($userId);

        if (! $week || ! $employee || $weekday < 0 || $weekday > 6) {
            $this->loadEntries();

            return;
        }

        $shift = filled($value) ? ShiftTemplate::query()->find($value) : null;

        if ($shift) {
            WeekTemplateEntry::query()->updateOrCreate(
                ['week_template_id' => $week->id, 'user_id' => $userId, 'weekday' => $weekday],
                ['shift_template_id' => $shift->id],
            );
        } else {
            WeekTemplateEntry::query()
                ->where('week_template_id', $week->id)
                ->where('user_id', $userId)
                ->where('weekday', $weekday)
                ->delete();
        }
    }

    private function selectedWeekTemplate(): ?WeekTemplate
    {
        return filled($this->selectedWeekTemplateId)
            ? WeekTemplate::query()->find($this->selectedWeekTemplateId)
            : null;
    }

    private function loadEntries(): void
    {
        $this->entries = [];

        $week = $this->selectedWeekTemplate();

        if (! $week) {
            $this->selectedWeekTemplateId = '';

            return;
        }

        foreach ($week->entries()->get() as $entry) {
            $this->entries[$entry->user_id][$entry->weekday] = (string) $entry->shift_template_id;
        }
    }

    public function render(): View
    {
        $weekdayLabels = collect(range(0, 6))->mapWithKeys(
            fn (int $weekday) => [$weekday => ucfirst(Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($weekday)->translatedFormat('D'))]
        );

        return view('livewire.schedules.templates', [
            'shiftTemplates' => ShiftTemplate::query()->withCount('entries')->orderBy('name')->get(),
            'weekTemplates' => WeekTemplate::query()->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('id')->get(),
            'weekdayLabels' => $weekdayLabels,
        ])->layout('layouts.app', ['title' => __('Modèles d\'horaire')]);
    }
}
