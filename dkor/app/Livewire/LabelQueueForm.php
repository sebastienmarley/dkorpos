<?php

namespace App\Livewire;

use App\Enums\LabelReason;
use App\Models\LabelRequest;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modale globale d'ajout d'étiquettes à la file d'impression, ouverte par <x-label-button>.
 */
class LabelQueueForm extends Component
{
    public bool $showModal = false;

    public ?Product $product = null;

    public int $quantity = 1;

    #[On('queue-label')]
    public function open(int $productId): void
    {
        $this->authorize('labels.create');

        $this->resetErrorBag();
        $this->product = Product::with('inventoryStock')->findOrFail($productId);
        $this->quantity = max(1, $this->product->inventoryStock->quantity_in_demo ?? 1);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('labels.create');

        $this->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);

        LabelRequest::enqueue($this->product, $this->quantity, LabelReason::Manual, auth()->user());

        Flux::toast(text: trans_choice(':count étiquette ajoutée à la file.|:count étiquettes ajoutées à la file.', $this->quantity), variant: 'success');
        $this->dispatch('label-queued');

        $this->showModal = false;
        $this->reset(['product', 'quantity']);
    }

    public function render(): View
    {
        return view('livewire.label-queue-form');
    }
}
