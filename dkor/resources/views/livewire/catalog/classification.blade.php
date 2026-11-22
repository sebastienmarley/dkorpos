<div class="sm:p-6">
    <div class="mb-6 flex items-center gap-2">
        <flux:heading level="1" size="xl">{{ __('Classification') }}</flux:heading>
        <flux:tooltip :content="__('Départements, catégories, couleurs et styles des produits')">
            <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
        </flux:tooltip>
    </div>

    {{-- Onglets --}}
    <div x-data="{ tab: @entangle('tab') }">
        <x-tabs class="mb-6" :save-on-switch="false" :tabs="array_filter([
            'departments' => $this->canViewDepartments() ? __('Départements et catégories') : null,
            'colors' => $this->canViewColors() ? __('Couleurs') : null,
            'styles' => $this->canViewStyles() ? __('Styles') : null,
        ])" />

        @if ($this->canViewDepartments())
            <div x-show="tab === 'departments'" x-cloak>
                <livewire:catalog.departments />
            </div>
        @endif

        @if ($this->canViewColors())
            <div x-show="tab === 'colors'" x-cloak>
                <livewire:catalog.colors />
            </div>
        @endif

        @if ($this->canViewStyles())
            <div x-show="tab === 'styles'" x-cloak>
                <livewire:catalog.styles />
            </div>
        @endif
    </div>
</div>
