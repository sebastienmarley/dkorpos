<?php

namespace App\Livewire\Accounting;

use App\Enums\Province;
use App\Models\Tax;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Taxes extends Component
{
    public string $filterProvince = '';

    /** Date de référence : n'affiche que les taxes en vigueur ce jour-là. */
    public string $filterDate = '';

    public bool $showModal = false;

    public string $province = 'QC';

    public string $name = '';

    public string $rate = '';

    public bool $isCompound = false;

    public string $startDate = '';

    public string $endDate = Tax::DEFAULT_END_DATE;

    public bool $showExpireModal = false;

    public ?int $expiringId = null;

    public string $expireDate = '';

    public function openCreate(): void
    {
        $this->authorize('taxes.create');

        $this->reset(['name', 'rate', 'isCompound']);
        $this->province = $this->filterProvince !== '' ? $this->filterProvince : Province::Quebec->value;
        $this->startDate = Carbon::today()->toDateString();
        $this->endDate = Tax::DEFAULT_END_DATE;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('taxes.create');

        $this->name = trim($this->name);

        $validated = $this->validate([
            'province' => ['required', Rule::enum(Province::class)],
            'name' => ['required', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,3'],
            'isCompound' => ['boolean'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
        ]);

        $conflict = Tax::overlapping($validated['province'], $validated['name'], $validated['startDate'], $validated['endDate']);

        if ($conflict) {
            $this->addError('name', __('Une taxe « :name » est déjà en vigueur dans cette province du :start au :end. Faites-la expirer avant d\'en créer une nouvelle.', [
                'name' => $conflict->name,
                'start' => $conflict->start_date->toDateString(),
                'end' => $conflict->end_date->toDateString(),
            ]));

            return;
        }

        Tax::create([
            'province' => $validated['province'],
            'name' => $validated['name'],
            'rate' => $validated['rate'],
            'is_compound' => $validated['isCompound'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
        ]);

        Flux::toast(text: __('Taxe créée.'), variant: 'success');

        $this->showModal = false;
    }

    public function openExpire(Tax $tax): void
    {
        $this->authorize('taxes.edit');

        $this->expiringId = $tax->id;
        $this->expireDate = Carbon::today()->max($tax->start_date)->toDateString();
        $this->resetValidation();
        $this->showExpireModal = true;
    }

    public function expire(): void
    {
        $this->authorize('taxes.edit');

        $tax = Tax::findOrFail($this->expiringId);

        $this->validate([
            'expireDate' => [
                'required', 'date',
                'after_or_equal:'.max($tax->start_date->toDateString(), Carbon::today()->toDateString()),
                'before:'.$tax->end_date->toDateString(),
            ],
        ], [
            'expireDate.after_or_equal' => __('La date de fin ne peut pas être passée ni précéder le début de la taxe.'),
            'expireDate.before' => __('La date de fin doit précéder la date de fin actuelle.'),
        ]);

        $tax->update(['end_date' => $this->expireDate]);

        Flux::toast(text: __('Taxe expirée.'), variant: 'success');

        $this->showExpireModal = false;
        $this->reset(['expiringId', 'expireDate']);
    }

    public function render(): View
    {
        $taxes = Tax::query()
            ->when($this->filterProvince !== '', fn ($query) => $query->where('province', $this->filterProvince))
            ->when($this->filterDate !== '', fn ($query) => $query->activeOn($this->filterDate))
            ->orderBy('province')
            ->orderBy('name')
            ->orderByDesc('start_date')
            ->get();

        return view('livewire.accounting.taxes', [
            'taxes' => $taxes,
            'provinces' => Province::cases(),
            'today' => Carbon::today(),
        ])->layout('layouts.app', ['title' => __('Taxes')]);
    }
}
