<?php

namespace App\Livewire\Labels;

use App\Actions\NotifyLabelsToPrint;
use App\Models\LabelRequest;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * File des étiquettes de prix à imprimer : impression groupée puis confirmation.
 */
class Index extends Component
{
    /** @var list<int> */
    public array $selected = [];

    /**
     * Demandes envoyées à l'impression, en attente de la confirmation « tout est imprimé ».
     *
     * @var list<int>
     */
    public array $printing = [];

    public bool $showConfirmModal = false;

    public function mount(): void
    {
        $this->selected = $this->pendingIds();
    }

    /** @return Collection<int, LabelRequest> */
    public function getRequests(): Collection
    {
        return LabelRequest::query()
            ->pending()
            ->with(['product.supplier', 'requester'])
            ->oldest()
            ->get();
    }

    public function updateQuantity(int $requestId, int $quantity): void
    {
        $this->authorize('labels.print');

        if ($quantity < 1 || $quantity > 99) {
            return;
        }

        LabelRequest::query()->pending()->whereKey($requestId)->update(['quantity' => $quantity]);
    }

    public function remove(int $requestId): void
    {
        $this->authorize('labels.print');

        LabelRequest::query()->pending()->whereKey($requestId)->delete();
        $this->selected = array_values(array_diff($this->selected, [$requestId]));

        app(NotifyLabelsToPrint::class)->clearIfQueueEmpty();
    }

    /**
     * Lance l'impression des demandes sélectionnées : la page les charge dans un cadre et ouvre la boîte d'impression.
     */
    public function print(): void
    {
        $this->authorize('labels.print');

        $this->printing = $this->pendingIds($this->selected);

        if ($this->printing === []) {
            Flux::toast(text: __('Aucune étiquette sélectionnée.'), variant: 'warning');

            return;
        }

        $this->dispatch('print-labels', url: route('labels.print', ['ids' => $this->printing]));
    }

    /** Appelé à la fermeture de la boîte d'impression. */
    public function askConfirmation(): void
    {
        if ($this->printing !== []) {
            $this->showConfirmModal = true;
        }
    }

    public function confirmPrinted(): void
    {
        $this->authorize('labels.print');

        $count = LabelRequest::markPrinted($this->printing, auth()->user());
        app(NotifyLabelsToPrint::class)->clearIfQueueEmpty();

        $this->selected = array_values(array_diff($this->selected, $this->printing));
        $this->reset(['printing', 'showConfirmModal']);

        Flux::toast(text: trans_choice(':count demande marquée comme imprimée.|:count demandes marquées comme imprimées.', $count), variant: 'success');
    }

    public function cancelPrinted(): void
    {
        $this->reset(['printing', 'showConfirmModal']);
    }

    #[On('label-queued')]
    public function refreshQueue(): void
    {
        $this->selected = array_values(array_unique([...$this->selected, ...$this->pendingIds()]));
    }

    /**
     * Identifiants des demandes en attente, limités à ceux donnés le cas échéant.
     *
     * @param  list<int>|null  $among
     * @return list<int>
     */
    private function pendingIds(?array $among = null): array
    {
        $ids = LabelRequest::query()
            ->pending()
            ->when($among !== null, fn ($query) => $query->whereKey($among))
            ->pluck('id')
            ->all();

        return array_values(array_map(intval(...), $ids));
    }

    public function render(): View
    {
        return view('livewire.labels.index', [
            'requests' => $this->getRequests(),
        ])->layout('layouts.app', ['title' => __('Étiquettes à imprimer')]);
    }
}
