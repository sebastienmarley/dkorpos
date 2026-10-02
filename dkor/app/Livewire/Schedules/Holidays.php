<?php

namespace App\Livewire\Schedules;

use App\Actions\GenerateQuebecHolidays;
use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\Schedule;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Holidays extends Component
{
    public int $year;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $date = '';

    public bool $isClosed = true;

    public function mount(): void
    {
        $this->year = Carbon::now()->year;
    }

    public function previousYear(): void
    {
        $this->year--;
    }

    public function nextYear(): void
    {
        $this->year++;
    }

    public function openCreate(): void
    {
        $this->authorize('holidays.create');

        $this->resetValidation();
        $this->reset(['editingId', 'name']);
        $this->date = Carbon::create($this->year, 1, 1)->toDateString();
        $this->isClosed = true;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorize('holidays.edit');

        $holiday = Holiday::query()->findOrFail($id);

        $this->resetValidation();
        $this->editingId = $holiday->id;
        $this->name = $holiday->name;
        $this->date = $holiday->date;
        $this->isClosed = $holiday->is_closed;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'holidays.edit' : 'holidays.create');

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'date')->ignore($this->editingId)],
            'isClosed' => ['boolean'],
        ], [
            'date.unique' => __('Un férié existe déjà à cette date.'),
        ]);

        if ($this->isClosed) {
            $stranded = $this->appointmentsStrandedBy($this->date);

            if ($stranded > 0) {
                $this->addError('isClosed', trans_choice(
                    ':count rendez-vous est déjà pris ce jour-là. Déplacez-le avant de fermer le magasin.|:count rendez-vous sont déjà pris ce jour-là. Déplacez-les avant de fermer le magasin.',
                    $stranded,
                ));

                return;
            }
        }

        $data = ['name' => $this->name, 'date' => $this->date, 'is_closed' => $this->isClosed];

        if ($this->editingId) {
            Holiday::query()->findOrFail($this->editingId)->update($data);
        } else {
            Holiday::create($data);
        }

        $this->year = (int) substr($this->date, 0, 4);
        $this->showModal = false;
        $this->reset(['editingId', 'name', 'date']);
    }

    public function delete(int $id): void
    {
        $this->authorize('holidays.delete');

        Holiday::query()->whereKey($id)->delete();
    }

    public function generateQuebec(): void
    {
        $this->authorize('holidays.create');

        $created = app(GenerateQuebecHolidays::class)->handle($this->year);

        Flux::toast(
            text: $created > 0
                ? trans_choice(':count férié ajouté pour :year.|:count fériés ajoutés pour :year.', $created, ['year' => $this->year])
                : __('Tous les fériés du Québec sont déjà présents pour :year.', ['year' => $this->year]),
            variant: $created > 0 ? 'success' : 'warning',
        );
    }

    /**
     * Rendez-vous à venir ce jour-là qui ne pourraient plus avoir lieu : ceux des employés
     * qui n'ont pas de quart de travail publié pour cette date.
     */
    private function appointmentsStrandedBy(string $date): int
    {
        if ($date < Carbon::today()->toDateString()) {
            return 0;
        }

        return Appointment::query()
            ->whereDate('date', $date)
            ->whereNotIn('user_id', Schedule::query()
                ->bookable()
                ->where('type', 'work')
                ->whereDate('date', $date)
                ->select('user_id'))
            ->count();
    }

    public function render(): View
    {
        return view('livewire.schedules.holidays', [
            'holidays' => Holiday::query()
                ->whereBetween('date', ["{$this->year}-01-01", "{$this->year}-12-31"])
                ->orderBy('date')
                ->get(),
        ])->layout('layouts.app', ['title' => __('Jours fériés')]);
    }
}
