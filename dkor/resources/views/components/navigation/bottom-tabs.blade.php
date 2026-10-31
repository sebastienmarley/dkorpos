@props([
    'tabs',
])

@php
    $columns = match (count($tabs) + 1) {
        2 => 'grid-cols-2',
        3 => 'grid-cols-3',
        4 => 'grid-cols-4',
        default => 'grid-cols-5',
    };
@endphp

{{-- Barre d'onglets des téléphones : les pages les plus utilisées à portée du pouce, le reste dans le menu. --}}
<nav class="fixed inset-x-0 bottom-0 z-20 border-t border-zinc-200 bg-zinc-50/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-sm md:hidden dark:border-zinc-700 dark:bg-zinc-900/95" data-test="bottom-tabs">
    <div class="grid {{ $columns }}">
        @foreach ($tabs as $tab)
            <a
                href="{{ $tab['url'] }}"
                @if ($tab['navigate']) wire:navigate @endif
                @class([
                    'relative flex h-16 flex-col items-center justify-center gap-1 px-1 text-[11px] font-medium',
                    'text-zinc-900 dark:text-white' => $tab['active'],
                    'text-zinc-500 dark:text-zinc-400' => ! $tab['active'],
                ])
                @if ($tab['active']) aria-current="page" @endif
            >
                <flux:icon :name="$tab['icon']" :variant="$tab['active'] ? 'solid' : 'outline'" class="size-6" />
                <span class="w-full truncate text-center">{{ $tab['label'] }}</span>

                @if ($tab['badge'])
                    <span class="absolute top-2 left-1/2 ms-2 size-2 rounded-full bg-amber-500"></span>
                @endif
            </a>
        @endforeach

        <flux:modal.trigger name="app-navigation">
            <button type="button" class="flex h-16 w-full flex-col items-center justify-center gap-1 px-1 text-[11px] font-medium text-zinc-500 dark:text-zinc-400">
                <flux:icon.bars-3 class="size-6" />
                <span>{{ __('Menu') }}</span>
            </button>
        </flux:modal.trigger>
    </div>
</nav>
