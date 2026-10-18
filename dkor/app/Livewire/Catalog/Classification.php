<?php

namespace App\Livewire\Catalog;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Page unique de classification des produits : départements et catégories, couleurs, styles.
 */
class Classification extends Component
{
    #[Url]
    public string $tab = '';

    public function mount(): void
    {
        abort_unless($this->availableTabs() !== [], 403);

        if (! in_array($this->tab, $this->availableTabs(), true)) {
            $this->tab = $this->availableTabs()[0];
        }
    }

    public function canViewDepartments(): bool
    {
        return auth()->user()->canAny(['departments.view', 'categories.view']);
    }

    public function canViewColors(): bool
    {
        return auth()->user()->can('colors.view');
    }

    public function canViewStyles(): bool
    {
        return auth()->user()->can('styles.view');
    }

    /** @return list<string> */
    private function availableTabs(): array
    {
        return array_keys(array_filter([
            'departments' => $this->canViewDepartments(),
            'colors' => $this->canViewColors(),
            'styles' => $this->canViewStyles(),
        ]));
    }

    public function render(): View
    {
        return view('livewire.catalog.classification')
            ->layout('layouts.app', ['title' => __('Classification')]);
    }
}
