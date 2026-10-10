<?php

namespace App\Livewire\Parts;

use App\Models\Part;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, Part> */
    public function getParts(): LengthAwarePaginator
    {
        return Part::query()
            ->with(['supplier', 'products'])
            ->when(filled(trim($this->search)), fn ($query) => $query->matching($this->search))
            ->orderBy('model')
            ->paginate(10);
    }

    /**
     * Une pièce choisie ou créée dans « Ajouter une pièce » ouvre sa fiche.
     */
    #[On('part-selected')]
    public function openPart(int $id): void
    {
        $this->redirectRoute('parts.show', ['part' => $id], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.parts.index', [
            'parts' => $this->getParts(),
        ])->layout('layouts.app', ['title' => __('Pièces')]);
    }
}
