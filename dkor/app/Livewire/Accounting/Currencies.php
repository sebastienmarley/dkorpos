<?php

namespace App\Livewire\Accounting;

use App\Models\Currency;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Currencies extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $rate = '1';

    public function openCreate(): void
    {
        $this->authorize('currencies.create');

        $this->editingId = null;
        $this->reset(['code', 'name', 'rate']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(Currency $currency): void
    {
        $this->authorize('currencies.edit');

        $this->editingId = $currency->id;
        $this->code = $currency->code;
        $this->name = $currency->name;
        $this->rate = (string) $currency->rate;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function toggleArchive(Currency $currency): void
    {
        $this->authorize('currencies.edit');

        $currency->update(['is_archived' => ! $currency->is_archived]);

        Flux::toast(
            text: $currency->is_archived ? __('Devise archivée.') : __('Devise restaurée.'),
            variant: 'success',
        );
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'currencies.edit' : 'currencies.create');

        $this->code = strtoupper(trim($this->code));

        $this->validate([
            'code' => ['required', 'string', 'size:3', 'alpha:ascii', Rule::unique('currencies', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999'],
        ]);

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'rate' => $this->rate,
        ];

        if ($this->editingId) {
            Currency::findOrFail($this->editingId)->update($data);
            Flux::toast(text: __('Devise mise à jour.'), variant: 'success');
        } else {
            Currency::create($data);
            Flux::toast(text: __('Devise créée.'), variant: 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'code', 'name', 'rate']);
    }

    public function render(): View
    {
        return view('livewire.accounting.currencies', [
            'currencies' => Currency::withCount('suppliers')->orderBy('is_archived')->orderBy('code')->get(),
        ])->layout('layouts.app', ['title' => __('Devises')]);
    }
}
