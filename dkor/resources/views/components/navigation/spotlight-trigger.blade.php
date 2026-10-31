<button
    type="button"
    x-data
    x-on:click="$dispatch('open-spotlight')"
    class="flex h-10 items-center gap-2 rounded-lg px-2.5 text-sm text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 md:w-64 md:border md:border-zinc-200 md:bg-white md:px-3 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white md:dark:border-zinc-700 md:dark:bg-zinc-800"
    aria-label="{{ __('Rechercher') }}"
    data-test="spotlight-button"
>
    <flux:icon.magnifying-glass class="size-5 md:size-4" />
    <span class="flex-1 text-start max-md:hidden">{{ __('Rechercher une page…') }}</span>
    <kbd class="rounded border border-zinc-200 px-1.5 font-sans text-xs max-md:hidden dark:border-zinc-600">⌘K</kbd>
</button>
