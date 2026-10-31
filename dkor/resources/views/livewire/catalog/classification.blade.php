<div class="sm:p-6">
    <div class="mb-6">
        <flux:heading level="1" size="xl">{{ __('Classification') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Départements, catégories, couleurs et styles des produits') }}</flux:text>
    </div>

    {{-- Onglets --}}
    <div x-data="{ tab: @entangle('tab') }">
        <div class="mb-6 flex overflow-x-auto overflow-y-hidden border-b border-zinc-200 [scrollbar-width:none] dark:border-zinc-700">
            @if ($this->canViewDepartments())
                <button
                    type="button"
                    @click="tab = 'departments'"
                    :class="tab === 'departments' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
                >{{ __('Départements et catégories') }}</button>
            @endif

            @if ($this->canViewColors())
                <button
                    type="button"
                    @click="tab = 'colors'"
                    :class="tab === 'colors' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
                >{{ __('Couleurs') }}</button>
            @endif

            @if ($this->canViewStyles())
                <button
                    type="button"
                    @click="tab = 'styles'"
                    :class="tab === 'styles' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
                >{{ __('Styles') }}</button>
            @endif
        </div>

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
