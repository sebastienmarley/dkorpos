<?php

namespace App\Livewire\Receptions;

use App\Models\Reception;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public Reception $reception;

    public string $reference = '';

    public string $notes = '';

    /** @var array<int, string> Quantité à recevoir par ligne de réception. */
    public array $quantities = [];

    /** @var array<int, string> Partie endommagée de la quantité reçue, par ligne de réception. */
    public array $damaged = [];

    public function mount(Reception $reception): void
    {
        $this->reception = $reception;
        $this->fillFromModel();
    }

    public function saveQuantities(): void
    {
        $this->authorize('receptions.create');

        $this->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantities.*' => ['required', 'integer', 'min:1', 'max:99999'],
            'damaged.*' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $this->attempt(function (): void {
            foreach ($this->reception->lines()->with('orderLine')->get() as $line) {
                $this->reception->setLineQuantity($line, (int) ($this->quantities[$line->id] ?? $line->quantity), (int) ($this->damaged[$line->id] ?? $line->quantity_damaged));
            }

            $this->reception->updateDetails($this->reference, $this->notes);
        }, __('Réception sauvegardée.'));
    }

    public function removeLine(int $lineId): void
    {
        $this->authorize('receptions.create');

        $line = $this->reception->lines()->findOrFail($lineId);

        $this->attempt(fn () => $this->reception->removeLine($line), __('Ligne retirée.'));
        $this->fillFromModel();
    }

    public function complete(): void
    {
        $this->authorize('receptions.create');

        $this->saveQuantities();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->attempt(fn () => $this->reception->complete(), __('Réception terminée : l\'inventaire est à jour.'));
        $this->fillFromModel();
    }

    public function discard(): void
    {
        $this->authorize('receptions.create');

        try {
            $this->reception->discard();
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->redirectRoute('receptions.index', navigate: true);
    }

    private function attempt(callable $action, string $successMessage): void
    {
        try {
            $action();
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->reception->refresh();
        Flux::toast(text: $successMessage, variant: 'success');
    }

    private function fillFromModel(): void
    {
        $this->reception->refresh()->unsetRelation('lines');
        $this->reference = $this->reception->reference ?? '';
        $this->notes = $this->reception->notes ?? '';
        $lines = $this->reception->lines()->get();
        $this->quantities = $lines->mapWithKeys(fn ($line) => [$line->id => (string) $line->quantity])->all();
        $this->damaged = $lines->mapWithKeys(fn ($line) => [$line->id => (string) $line->quantity_damaged])->all();
    }

    public function render(): View
    {
        $this->reception->load(['supplier', 'receiver', 'lines.product', 'lines.orderLine.order']);

        return view('livewire.receptions.show')
            ->layout('layouts.app', ['title' => $this->reception->number]);
    }
}
