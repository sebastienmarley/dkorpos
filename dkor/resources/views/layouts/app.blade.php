<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="max-sm:px-4 max-sm:pt-4 max-md:pb-24">
        {{ $slot }}

        @can('labels.create')
            <livewire:label-queue-form />
        @endcan
    </flux:main>
</x-layouts::app.sidebar>
