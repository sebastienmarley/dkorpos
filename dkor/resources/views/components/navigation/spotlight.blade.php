@props([
    'sections',
])

{{-- Recherche « spotlight » des pages accessibles : ⌘K ou Ctrl+K, flèches pour choisir, Entrée pour ouvrir. --}}
<flux:modal name="spotlight" :closable="false" class="w-full max-w-xl p-0! md:mt-[12vh] md:mb-auto" data-test="spotlight">
    {{-- Dans le modal : il crée sa propre portée Alpine, hors de laquelle les x-ref seraient invisibles. --}}
    <div x-data="spotlight" x-on:open-spotlight.window="show()" x-on:keydown.window="onGlobalKeydown($event)">
        <div class="flex items-center gap-2 border-b border-zinc-200 px-4 dark:border-zinc-700">
            <flux:icon.magnifying-glass class="size-5 text-zinc-400" />
            <input
                x-ref="input"
                x-model="query"
                x-on:input="filter()"
                x-on:keydown.down.prevent="move(1)"
                x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter.prevent="openActive()"
                type="text"
                autocomplete="off"
                autofocus
                placeholder="{{ __('Rechercher une page…') }}"
                class="h-14 w-full bg-transparent text-base outline-none placeholder:text-zinc-400"
            />
        </div>

        <div x-ref="results" class="max-h-[60dvh] overflow-y-auto p-2">
            @foreach ($sections as $section)
                <div data-spotlight-section>
                    @if ($section['heading'])
                        <div class="px-3 pt-3 pb-1 text-xs font-medium text-zinc-400">{{ $section['heading'] }}</div>
                    @endif

                    @foreach ($section['items'] as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @if ($item['navigate']) wire:navigate @endif
                            data-spotlight-item
                            data-search="{{ \Illuminate\Support\Str::of($item['label'].' '.$section['heading'])->ascii()->lower() }}"
                            x-on:mousemove="activate($el)"
                            class="flex h-11 items-center gap-3 rounded-lg px-3 text-sm text-zinc-700 data-active:bg-zinc-800/5 data-active:text-zinc-900 dark:text-zinc-300 dark:data-active:bg-white/10 dark:data-active:text-white"
                        >
                            <flux:icon :name="$item['icon']" variant="outline" class="size-5 text-zinc-400" />
                            <span class="flex-1 truncate">{{ $item['label'] }}</span>
                            @if ($item['badge'])
                                <flux:badge color="amber" size="sm">{{ $item['badge'] }}</flux:badge>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach

            <div x-show="visibleCount === 0" x-cloak class="px-3 py-8 text-center text-sm text-zinc-500">
                {{ __('Aucune page trouvée.') }}
            </div>
        </div>
    </div>
</flux:modal>
