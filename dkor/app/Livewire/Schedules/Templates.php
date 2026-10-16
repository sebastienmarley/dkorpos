<?php

namespace App\Livewire\Schedules;

use App\Models\ShiftTemplate;
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

    /** @var array<int|string, string> Quart type choisi pour chaque jour (0 = dimanche), ou '' pour un jour de congé. */
    public array $entries = [];

    // ── Quarts type ────────────────────────────────────────────────────────

    public function openCreateShift(): void
    {
        $this->authorize('schedule_templates.create');

        $this->resetValidation();
        $this->reset(['editingShiftId', 'shiftName', 'shiftStart', 'shiftEnd', 'shiftBreak']);
        $this->showShiftModal = true;
    }

    public function openEditShift(int $id): void
    {
        $this->authorize('schedule_templates.edit');

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
        $this->authorize($this->editingShiftId ? 'schedule_templates.edit' : 'schedule_templates.create');

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
        $this->authorize('schedule_templates.delete');

        ShiftTemplate::query()->whereKey($id)->delete();

        $this->loadEntries();
        Flux::toast(text: __('Quart type supprimé.'), variant: 'success');
    }

    // ── Semaines type ──────────────────────────────────────────────────────

    public function openCreateWeek(): void
    {
        $this->authorize('schedule_templates.create');

        $this->resetValidation();
        $this->reset(['editingWeekId', 'weekName']);
        $this->showWeekModal = true;
    }

    public function openRenameWeek(): void
    {
        $this->authorize('schedule_templates.edit');

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
        $this->authorize($this->editingWeekId ? 'schedule_templates.edit' : 'schedule_templates.create');

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
        $this->authorize('schedule_templates.delete');

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
     * Enregistre aussitôt le quart type choisi pour un jour.
     *
     * Selon l'état côté navigateur, Livewire fournit la case modifiée (clé « jour ») ou tout le tableau (sans clé).
     */
    public function updatedEntries(mixed $value, ?string $key = null): void
    {
        $this->authorize('schedule_templates.edit');

        if ($key !== null && $key !== '') {
            $this->saveEntry((int) $key, (string) ($value ?? ''));

            return;
        }

        foreach (is_array($value) ? $value : [] as $weekday => $shiftId) {
            $this->saveEntry((int) $weekday, (string) ($shiftId ?? ''));
        }
    }

    private function saveEntry(int $weekday, string $value): void
    {
        $week = $this->selectedWeekTemplate();

        if (! $week || $weekday < 0 || $weekday > 6) {
            $this->loadEntries();

            return;
        }

        $shift = filled($value) ? ShiftTemplate::query()->find($value) : null;

        if ($shift) {
            WeekTemplateEntry::query()->updateOrCreate(
                ['week_template_id' => $week->id, 'weekday' => $weekday],
                ['shift_template_id' => $shift->id],
            );
        } else {
            WeekTemplateEntry::query()
                ->where('week_template_id', $week->id)
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
            $this->entries[$entry->weekday] = (string) $entry->shift_template_id;
        }
    }

    public function render(): View
    {
        $weekdayLabels = collect(range(0, 6))->mapWithKeys(
            fn (int $weekday) => [$weekday => ucfirst(Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($weekday)->translatedFormat('D'))]
        );

        $shiftTemplates = ShiftTemplate::query()->withCount('entries')->orderBy('name')->get();

        // Heures payées de la semaine type sélectionnée, pauses déduites.
        $weeklyMinutes = collect($this->entries)
            ->map(fn (string $shiftId) => $shiftTemplates->firstWhere('id', (int) $shiftId))
            ->filter()
            ->sum(fn (ShiftTemplate $shift): int => max(0, (int) Carbon::parse($shift->start_time)->diffInMinutes(Carbon::parse($shift->end_time)) - $shift->break_minutes));

        return view('livewire.schedules.templates', [
            'shiftTemplates' => $shiftTemplates,
            'weekTemplates' => WeekTemplate::query()->withCount('entries')->orderBy('name')->get(),
            'weekdayLabels' => $weekdayLabels,
            'weeklyMinutes' => $weeklyMinutes,
        ])->layout('layouts.app', ['title' => __('Modèles d\'horaire')]);
    }
}
