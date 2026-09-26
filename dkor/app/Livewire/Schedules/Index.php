<?php

namespace App\Livewire\Schedules;

use App\Models\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public string $weekStart;

    public function mount(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek()->toDateString();
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
        $this->weekStart = Carbon::now()->startOfWeek()->toDateString();
    }

    public function render(): View
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $days = Collection::times(7, fn ($i) => $start->copy()->addDays($i - 1));

        $schedules = Schedule::query()
            ->where('user_id', auth()->id())
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $end->copy()->addDay()->toDateString())
            ->get()
            ->keyBy(fn ($s) => Carbon::parse($s->date)->toDateString());

        return view('livewire.schedules.index', [
            'days' => $days,
            'schedules' => $schedules,
            'startDate' => $start,
            'endDate' => $end,
            'isCurrentWeek' => $start->isSameWeek(Carbon::now()),
        ])->layout('layouts.app', ['title' => __('Horaire')]);
    }
}
