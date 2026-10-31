<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="max-md:pb-24">
        {{ $slot }}

        @can('labels.create')
            <livewire:label-queue-form />
        @endcan
    </flux:main>
</x-layouts::app.sidebar>
